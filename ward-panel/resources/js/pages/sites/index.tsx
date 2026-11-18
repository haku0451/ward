import { useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

type SiteRow = {
    id: number;
    name: string;
    hostname: string;
    origin_url: string;
    waf_mode: string | null;
    node: string | null;
};

export default function SitesIndex({
    sites,
    nodes,
}: {
    sites: SiteRow[];
    nodes: { id: number; name: string }[];
}) {
    const { auth } = usePage().props;
    const isAdmin = auth.user?.role === 'admin';
    const form = useForm({
        name: '',
        hostname: '',
        origin_url: 'http://127.0.0.1:9080',
        waf_mode: '',
        node_id: '',
    });

    return (
        <AppLayout title="保護対象サイト">
            <h1 className="mb-6 text-2xl font-semibold">保護対象サイト</h1>
            {isAdmin ? (
                <form
                    className="mb-6 grid gap-3 md:grid-cols-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/sites', { onSuccess: () => form.reset() });
                    }}
                >
                    <input
                        className="rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm"
                        placeholder="名前"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />
                    <input
                        className="rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm"
                        placeholder="hostname"
                        value={form.data.hostname}
                        onChange={(event) =>
                            form.setData('hostname', event.target.value)
                        }
                    />
                    <input
                        className="rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm"
                        placeholder="origin URL"
                        value={form.data.origin_url}
                        onChange={(event) =>
                            form.setData('origin_url', event.target.value)
                        }
                    />
                    <select
                        className="rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm"
                        value={form.data.waf_mode}
                        onChange={(event) =>
                            form.setData('waf_mode', event.target.value)
                        }
                    >
                        <option value="">モード: 継承</option>
                        <option value="detection_only">検知のみ</option>
                        <option value="blocking">遮断</option>
                    </select>
                    <select
                        className="rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm"
                        value={form.data.node_id}
                        onChange={(event) =>
                            form.setData('node_id', event.target.value)
                        }
                    >
                        <option value="">全ノード</option>
                        {nodes.map((node) => (
                            <option key={node.id} value={node.id}>
                                {node.name}
                            </option>
                        ))}
                    </select>
                    <button
                        type="submit"
                        className="rounded-md bg-emerald-600 px-4 py-2 text-sm"
                        disabled={form.processing}
                    >
                        登録
                    </button>
                    {form.errors.hostname ? (
                        <p className="text-sm text-red-400 md:col-span-5">
                            {form.errors.hostname}
                        </p>
                    ) : null}
                    {form.errors.origin_url ? (
                        <p className="text-sm text-red-400 md:col-span-5">
                            {form.errors.origin_url}
                        </p>
                    ) : null}
                </form>
            ) : null}
            <div className="overflow-x-auto rounded-xl border border-zinc-800">
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-zinc-900 text-zinc-400">
                        <tr>
                            <th className="px-3 py-2">名前</th>
                            <th className="px-3 py-2">ホスト</th>
                            <th className="px-3 py-2">オリジン</th>
                            <th className="px-3 py-2">モード</th>
                            <th className="px-3 py-2">ノード</th>
                        </tr>
                    </thead>
                    <tbody>
                        {sites.map((site) => (
                            <tr
                                key={site.id}
                                className="border-t border-zinc-800"
                            >
                                <td className="px-3 py-2">{site.name}</td>
                                <td className="px-3 py-2">{site.hostname}</td>
                                <td className="px-3 py-2">{site.origin_url}</td>
                                <td className="px-3 py-2">
                                    {site.waf_mode ?? 'default'}
                                </td>
                                <td className="px-3 py-2">
                                    {site.node ?? '全ノード'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
