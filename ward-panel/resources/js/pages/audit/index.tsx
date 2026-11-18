import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

type LogRow = {
    id: number;
    action: string;
    user: string | null;
    metadata: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string | null;
};

type Paginator = {
    data: LogRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function AuditIndex({ logs }: { logs: Paginator }) {
    return (
        <AppLayout title="監査ログ">
            <h1 className="mb-6 text-2xl font-semibold">監査ログ</h1>
            <div className="overflow-x-auto rounded-xl border border-zinc-800">
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-zinc-900 text-zinc-400">
                        <tr>
                            <th className="px-3 py-2">時刻</th>
                            <th className="px-3 py-2">操作</th>
                            <th className="px-3 py-2">ユーザー</th>
                            <th className="px-3 py-2">詳細</th>
                        </tr>
                    </thead>
                    <tbody>
                        {logs.data.map((log) => (
                            <tr
                                key={log.id}
                                className="border-t border-zinc-800"
                            >
                                <td className="whitespace-nowrap px-3 py-2 text-zinc-400">
                                    {log.created_at}
                                </td>
                                <td className="px-3 py-2">{log.action}</td>
                                <td className="px-3 py-2">{log.user}</td>
                                <td className="max-w-md truncate px-3 py-2 font-mono text-xs">
                                    {log.metadata
                                        ? JSON.stringify(log.metadata)
                                        : ''}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="mt-4 flex flex-wrap gap-2 text-sm">
                {logs.links.map((link) => (
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
