<?php

namespace App\Traits;

use App\Services\Reports\ReportMailer;
use App\Services\Reports\ReportRenderer;
use App\Services\Reports\RenderedReport;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Download and email actions shared by the Reports pages. Each controller
 * supplies a closure that renders its report in the requested format; the
 * emailed file is always rebuilt on the server from the same filters, never
 * uploaded by the browser.
 */
trait ExportsReports
{
    protected function reportFormat(Request $request): string
    {
        return $request->validate([
            'format' => 'nullable|in:' . implode(',', ReportRenderer::FORMATS),
        ])['format'] ?? 'pdf';
    }

    /**
     * A calendar date in the workspace's date format. Deliberately no timezone
     * shift (unlike formatDateTime()): a timesheet date is a day, not an instant,
     * and converting midnight UTC would move it to the previous day west of UTC.
     */
    protected function reportDate($date): ?string
    {
        return $date ? Carbon::parse($date)->format(settings()['dateFormat'] ?? 'Y-m-d') : null;
    }

    /** The workspace currency code, shown in amount column headings. */
    protected function reportCurrency(): string
    {
        return settings()['defaultCurrency'] ?? 'INR';
    }

    /** @param  Closure(string $format): RenderedReport  $render */
    protected function emailReport(Request $request, string $title, Closure $render): JsonResponse
    {
        $validated = $request->validate([
            'format' => 'required|in:' . implode(',', ReportRenderer::FORMATS),
            'recipient' => 'required|in:me,other',
            'email' => 'required_if:recipient,other|nullable|email:rfc|max:255',
            'message' => 'nullable|string|max:1000',
        ], [
            'email.required_if' => __('Enter the email address to send the report to.'),
        ]);

        $user = $request->user();
        $to = $validated['recipient'] === 'me' ? $user->email : $validated['email'];

        try {
            app(ReportMailer::class)->send($render($validated['format']), $title, $to, $user, $validated['message'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => __('Report sent to :email.', ['email' => $to]),
            'email' => $to,
        ]);
    }
}
