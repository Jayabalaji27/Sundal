<?php

/*
 * Feature flags — set to false to disable a module from the sidebar.
 * Routes remain live (so existing bookmarks don't break) but nav entries disappear.
 * Flip to true to re-enable without a code revert.
 *
 * The second block keeps each role's menu to the work it does (business
 * validation report, 2026-10-18): modules rated "Good to have" or "Not needed"
 * start hidden and can be switched back on per installation.
 */
return [
    'portfolios'       => false,   // public project showcase — premature for v1
    'referral'         => false,   // referral program — enable when ready to grow
    'currency_mgmt'    => false,   // multi-currency management — India launch is single-currency
    'contract_types'   => false,   // moved to Settings → General dropdown

    'bugs_menu'        => false,   // bugs are tasks of type bug: Board, Backlog, Sprints, Tasks list (Bugs switch) and the Create menu
    'calendar'         => false,   // task calendar
    'chat'             => false,   // most teams already use Slack / Teams (Slack integration exists)
    'meetings'         => true,    // Meetings menu and project tab; they say so until Zoom or Google Meet is connected
    'knowledge_base'   => false,
    'notes'            => false,   // duplicates task comments and project files
    'forms'            => false,   // intake forms
    'ai_assistant'     => true,    // "AI mode" switch, AI Assistant conversations and voice (owners and managers)
    'ai'               => false,   // AI Tools page (Risk Radar, Resource Conflicts, agents) and the floating generator
    'project_reports'  => false,   // same columns as the Projects list
    'budgets_page'     => false,   // budgets live in each project's Budget tab
    'contracts'        => true,    // owner and client (e-signature); off for managers and members
    'newsletter'       => false,   // platform admin: use a mailing tool
];
