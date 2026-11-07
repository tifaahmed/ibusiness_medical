<?php

namespace App\Http\Controllers\Admin\QuickAdd;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsOutbox;
use App\Support\QuickAdd;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The customer-SMS control panel. Top admin only: it decides whether real messages (that cost money) leave. */
class SmsOutboxController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeTopAdmin($request);

        $status = in_array($request->input('status'), ['queued', 'sent', 'failed'], true) ? $request->input('status') : '';
        $page = SmsMessage::query()->with(['author:id,name', 'user:id,name,slug'])
            ->when($status, fn ($q, $s) => $q->where('status', $s))->latest('id')->paginate(30)->withQueryString();

        return Inertia::render('Admin/Sms/Index', [
            'enabled' => QuickAdd::smsEnabled(),
            'configured' => SmsGateway::isConfigured(),
            'rows' => collect($page->items())->map(fn (SmsMessage $m) => [
                'id' => $m->id, 'phone' => $m->phone, 'body' => $m->body, 'characters' => $m->characters, 'segments' => $m->segments,
                'status' => $m->status, 'error' => $m->error, 'by' => $m->author?->name, 'member' => $m->user ? ['name' => $m->user->name, 'slug' => $m->user->slug] : null,
                'created_at' => $m->created_at?->format('Y-m-d H:i'), 'sent_at' => $m->sent_at?->format('Y-m-d H:i'),
            ])->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'links' => $page->linkCollection()->toArray()],
            'counts' => SmsMessage::query()->selectRaw('status, count(*) as n, coalesce(sum(segments),0) as segs')->groupBy('status')->get()->keyBy('status')->map(fn ($r) => ['n' => (int) $r->n, 'segments' => (int) $r->segs])->all(),
            'status' => $status,
        ]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        $this->authorizeTopAdmin($request);
        $on = $request->boolean('enabled');
        SiteSettings::put(QuickAdd::SMS_ENABLED, $on ? '1' : '0', Setting::TYPE_BOOLEAN);
        SiteSettings::forget();

        return back()->with('success', $on ? 'Sending is ON. New messages leave immediately.' : 'Sending is OFF. Messages are only recorded as queued.');
    }

    /** Send what waited while the switch was off (or failed). Does nothing unless sending is on. */
    public function sendQueued(Request $request, SmsOutbox $outbox): RedirectResponse
    {
        $this->authorizeTopAdmin($request);

        if (! QuickAdd::smsEnabled() || ! SmsGateway::isConfigured()) {
            return back()->with('error', 'Turn sending on (and set the gateway) first.');
        }

        $sent = 0;
        SmsMessage::query()->whereIn('status', [SmsMessage::STATUS_QUEUED, SmsMessage::STATUS_FAILED])->orderBy('id')->limit(200)->get()
            ->each(function (SmsMessage $m) use ($outbox, &$sent) {
                $sent += $outbox->dispatch($m)->status === SmsMessage::STATUS_SENT ? 1 : 0;
            });

        return back()->with('success', "{$sent} message(s) sent.");
    }

    private function authorizeTopAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);
    }
}
