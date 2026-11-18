import { router, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

type EventRow = {
    id: number;
    event_id: string;
    occurred_at: string;
    node: string | null;
    hostname: string | null;
    client_ip: string;
    method: string;
    path: string;
    rule_id: string | null;
    rule_message: string | null;
    action: string;
    http_status: number | null;
    correlation_id: string | null;
};

type Paginator = {
    data: EventRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function EventsIndex({
    events,
    filters,
    actions,
}: {
    events: Paginator;
    filters: { action: string; node_id: string | number; q: string };
    actions: string[];
}) {
    const form = useForm({
        q: filters.q,
        action: filters.action,
    });

    return (
        <AppLayout title="攻撃イベント">
            <h1 className="mb-6 text-2xl font-semibold">攻撃イベント</h1>
            <form
                className="mb-4 flex flex-wrap gap-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.get('/events', { preserveState: true });
                }}
            >
                <input
                    className="rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm"
                    placeholder="IP / パス / ルール"
                    value={form.data.q}
                    onChange={(event) => form.setData('q', event.target.value)}
                />
                <select
                    className="rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm"
                    value={form.data.action}
                    onChange={(event) =>
                        form.setData('action', event.target.value)
                    }
                >
                    <option value="">すべてのアクション</option>
                    {actions.map((action) => (
                        <option key={action} value={action}>
                            {action}
                        </option>
                    ))}
                </select>
                <button
                    type="submit"
                    className="rounded-md bg-zinc-800 px-4 py-2 text-sm"
                >
                    絞り込む
                </button>
            </form>
            <div className="overflow-x-auto rounded-xl border border-zinc-800">
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-zinc-900 text-zinc-400">
                        <tr>
                            <th className="px-3 py-2">時刻</th>
                            <th className="px-3 py-2">ノード</th>
                            <th className="px-3 py-2">IP</th>
                            <th className="px-3 py-2">メソッド</th>
                            <th className="px-3 py-2">パス</th>
                            <th className="px-3 py-2">ルール</th>
                            <th className="px-3 py-2">アクション</th>
                        </tr>
                    </thead>
                    <tbody>
                        {events.data.map((event) => (
                            <tr
                                key={event.id}
                                className="border-t border-zinc-800"
                            >
                                <td className="whitespace-nowrap px-3 py-2 text-zinc-400">
                                    {event.occurred_at}
                                </td>
                                <td className="px-3 py-2">{event.node}</td>
                                <td className="px-3 py-2">{event.client_ip}</td>
                                <td className="px-3 py-2">{event.method}</td>
                                <td className="max-w-xs truncate px-3 py-2">
                                    {event.path}
                                </td>
                                <td className="px-3 py-2">
                                    {event.rule_id}
                                    {event.rule_message ? (
                                        <span className="block text-xs text-zinc-500">
                                            {event.rule_message}
                                        </span>
                                    ) : null}
                                </td>
                                <td className="px-3 py-2">{event.action}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="mt-4 flex flex-wrap gap-2 text-sm">
                {events.links.map((link) => (
                    <button
                        key={link.label}
                        type="button"
                        disabled={!link.url}
                        className={`rounded px-2 py-1 ${link.active ? 'bg-emerald-700' : 'bg-zinc-800'} disabled:opacity-40`}
                        onClick={() => {
                            if (link.url) {
                                router.visit(link.url);
                            }
                        }}
                    >
                        {link.label.replace(/<[^>]*>/g, '')}
                    </button>
                ))}
            </div>
        </AppLayout>
    );
}
