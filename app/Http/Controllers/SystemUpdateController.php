<?php

namespace App\Http\Controllers;

use App\Actions\ActionException;
use App\Services\SystemUpdate;
use Illuminate\Http\Request;

/**
 * System update page: after uploading a new version, the superadmin runs its
 * database changes from the browser (for servers without a terminal).
 * Plain Blade, not Inertia, so it still opens while the database is on the
 * old version; CheckInstallation sends the superadmin here when migrations
 * are pending. Not behind a permission: a new version's permissions only
 * exist once this page has run.
 */
class SystemUpdateController extends Controller
{
    public const DEMO_CONFIRM = 'DEMO';

    public function __construct(private readonly SystemUpdate $update) {}

    public function show(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return view('system-update', [
            'pending' => $this->update->pending(),
            'storageLink' => $this->update->storageLink(),
            'lastRun' => $this->update->lastRun(),
            'lastDemo' => $this->update->lastDemoData(),
            'demoPassword' => (string) config('demo.password'),
        ]);
    }

    public function run(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        try {
            $this->update->run($request->user()->id);
        } catch (ActionException $e) {
            return redirect()->route('system-update.show')->with('system_update_error', $e->getMessage());
        }

        return redirect()->route('system-update.show');
    }

    /** Replaces all work data with the demo company, after a typed confirmation. */
    public function demoData(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        if (trim((string) $request->input('confirm')) !== self::DEMO_CONFIRM) {
            return redirect()->route('system-update.show')
                ->with('system_update_error', __('Type :word to confirm loading the demo data.', ['word' => self::DEMO_CONFIRM]));
        }

        try {
            $this->update->loadDemoData($request->user()->id);
        } catch (ActionException $e) {
            return redirect()->route('system-update.show')->with('system_update_error', $e->getMessage());
        }

        return redirect()->to(route('system-update.show') . '#demo-data');
    }
}
