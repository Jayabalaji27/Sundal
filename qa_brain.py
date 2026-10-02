#!/usr/bin/env python3
"""
QA Brain: adversarial exploratory-scenario generator for the /qa-analyst skill.

Usage:
    cat context.json | python qa_brain.py              # initial scenario batch
    cat context.json | python qa_brain.py --followup    # deeper probes after results

Reads a JSON app map (+ already-tested scenarios, + prior results on --followup)
from stdin, calls an Azure OpenAI chat completion, and prints a strict JSON array
of ranked adversarial test scenarios to stdout. Never prints credentials.
"""

import json
import os
import sys
import urllib.error
import urllib.request

from dotenv import load_dotenv

load_dotenv()

SCENARIO_SCHEMA_HINT = """
Return ONLY a strict JSON array (no markdown fences, no prose) of up to 15 objects,
ranked most-valuable first, each shaped exactly like:
{
  "id": "string, short unique slug",
  "hypothesis": "what bug you suspect and why",
  "category": "one of: access-control | data-integrity | validation | ui-state | idempotency | concurrency | edge-case",
  "severity_if_true": "Blocker | Critical | Major | Minor",
  "steps": ["numbered, concrete browser actions a human/agent can literally perform"],
  "expected": "what correct behavior looks like",
  "watch_for": "the specific signal that would prove the hypothesis true (console error, wrong status code, wrong data, etc.)"
}
"""

SYSTEM_PROMPT = (
    "You are an adversarial senior QA engineer probing a multi-tenant Laravel + "
    "Inertia/React SaaS app (project management: workspaces, tasks, bugs, timesheets, "
    "budgets, invoices, contracts, permissions). You are given an 'app map' describing "
    "pages, forms, roles, and constraints already discovered by a crawl, plus a list of "
    "scenarios already tested (or, on a follow-up call, their actual results). Your job "
    "is to invent HIGH-VALUE, NON-OBVIOUS test scenarios likely to surface real defects: "
    "access-control gaps (a lower-privileged role reaching an action/page it shouldn't), "
    "cross-tenant/workspace data leakage, permission-system disagreements (this app has "
    "two parallel permission systems that can drift out of sync), stale/incorrect data "
    "after CRUD, race conditions, validation bypass, and broken empty/error states. "
    "Do not repeat anything already tested. Never propose anything destructive to shared "
    "production data or anything targeting a real external system — this is a local test "
    "instance only."
)

FOLLOWUP_SUFFIX = (
    "\n\nYou are now given the ACTUAL RESULTS of your previous scenario batch under "
    "'results'. Reason about what really happened (pass/fail/blocked, observed behavior, "
    "console/network anomalies) and propose deeper follow-up probes that dig further into "
    "anything suspicious or incompletely verified. Do not repeat scenarios already run."
)


def get_config():
    endpoint = os.environ.get("AZURE_OPENAI_ENDPOINT", "").rstrip("/")
    api_key = os.environ.get("AZURE_OPENAI_API_KEY", "")
    api_version = os.environ.get("AZURE_OPENAI_API_VERSION", "2025-01-01-preview")
    deployment = os.environ.get("AZURE_OPENAI_DEPLOYMENT", "")

    missing = [
        name
        for name, val in (
            ("AZURE_OPENAI_ENDPOINT", endpoint),
            ("AZURE_OPENAI_API_KEY", api_key),
            ("AZURE_OPENAI_DEPLOYMENT", deployment),
        )
        if not val
    ]
    if missing:
        sys.stderr.write(
            "qa_brain.py: missing required env var(s): "
            + ", ".join(missing)
            + " (checked process env and .env via python-dotenv)\n"
        )
        sys.exit(2)

    return endpoint, api_key, api_version, deployment


def call_azure_openai(endpoint, api_key, api_version, deployment, user_content):
    url = f"{endpoint}/openai/deployments/{deployment}/chat/completions?api-version={api_version}"
    body = json.dumps(
        {
            "messages": [
                {"role": "system", "content": SYSTEM_PROMPT},
                {"role": "user", "content": user_content},
            ],
            "temperature": 0.7,
            "max_tokens": 4000,
        }
    ).encode("utf-8")

    req = urllib.request.Request(
        url,
        data=body,
        method="POST",
        headers={
            "Content-Type": "application/json",
            "api-key": api_key,
        },
    )
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            raw = resp.read().decode("utf-8")
    except urllib.error.HTTPError as e:
        detail = e.read().decode("utf-8", errors="replace")
        sys.stderr.write(f"qa_brain.py: Azure OpenAI HTTP {e.code}: {detail}\n")
        sys.exit(3)
    except urllib.error.URLError as e:
        sys.stderr.write(f"qa_brain.py: could not reach Azure OpenAI endpoint: {e.reason}\n")
        sys.exit(3)

    data = json.loads(raw)
    try:
        content = data["choices"][0]["message"]["content"]
    except (KeyError, IndexError) as e:
        sys.stderr.write(f"qa_brain.py: unexpected response shape: {raw[:500]}\n")
        sys.exit(3)

    return content


def extract_json_array(text):
    text = text.strip()
    if text.startswith("```"):
        text = text.strip("`")
        if text.lower().startswith("json"):
            text = text[4:]
        text = text.strip()
    start = text.find("[")
    end = text.rfind("]")
    if start == -1 or end == -1:
        sys.stderr.write("qa_brain.py: model did not return a JSON array\n")
        sys.stderr.write(text[:1000] + "\n")
        sys.exit(4)
    return text[start : end + 1]


def main():
    followup = "--followup" in sys.argv[1:]

    try:
        context = json.load(sys.stdin)
    except json.JSONDecodeError as e:
        sys.stderr.write(f"qa_brain.py: invalid JSON on stdin: {e}\n")
        sys.exit(1)

    endpoint, api_key, api_version, deployment = get_config()

    prompt = SCENARIO_SCHEMA_HINT + "\n\nContext:\n" + json.dumps(context, indent=2)
    if followup:
        prompt += FOLLOWUP_SUFFIX

    content = call_azure_openai(endpoint, api_key, api_version, deployment, prompt)
    array_text = extract_json_array(content)

    try:
        scenarios = json.loads(array_text)
    except json.JSONDecodeError as e:
        sys.stderr.write(f"qa_brain.py: model output was not valid JSON: {e}\n")
        sys.stderr.write(array_text[:1000] + "\n")
        sys.exit(4)

    print(json.dumps(scenarios, indent=2))


if __name__ == "__main__":
    main()
