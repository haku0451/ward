import { router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

type Result = { node: string | null; status: string; message: string | null };

type BlockRow = {
    id: number;
    cidr: string;
    source: string;
    reason: string | null;
    node: string | null;
    site: string | null;
    expires_at: string | null;
    revoked_at: string | null;
    apply_status: string;
    results: Result[];
    active: boolean;
};

type Paginator = {
    data: BlockRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function BlocksIndex({
    blocks,
    nodes,
    sites,
}: {
    blocks: Paginator;
    nodes: { id: number; name: string }[];
    sites: { id: number; name: string; hostname: string }[];
}) {
    const { auth } = usePage().props;
    const isAdmin = auth.user?.role === 'admin';
    const form = useForm({
        cidr: '',
        reason: '',
        node_id: '',
        site_id: '',
        expires_at: '',
    });

    return (
        <AppLayout title="IP遮断">
            <h1 className="mb-6 text-2xl font-semibold">IP / CIDR 遮断</h1>
            {isAdmin ? (
                <form
                    className="mb-6 grid gap-3 md:grid-cols-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/blocks', { onSuccess: () => form.reset() });
                    }}
                >
                    <input
                        className="rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm"
                        placeholder="203.0.113.0/24"
                        value={form.data.cidr}
                        onChange={(event) =>
                            form.setData('cidr', event.target.value)
                        }
                    />
                    <input
                        className="rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm"
                        placeholder="理由"
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                    />
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
                    <select
                        className="rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm"
                        value={form.data.site_id}
                        onChange={(event) =>
                            form.setData('site_id', event.target.value)
                        }
                    >
                        <option value="">全サイト</option>
                        {sites.map((site) => (
                            <option key={site.id} value={site.id}>
                                {site.hostname}
                            </option>
                        ))}
                    </select>
                    <input
                        className="rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm"
                        type="datetime-local"
                        value={form.data.expires_at}
                        onChange={(event) =>
                            form.setData('expires_at', event.target.value)
                        }
                    />
                    <button
                        type="submit"
                        className="rounded-md bg-emerald-600 px-4 py-2 text-sm"
                        disabled={form.processing}
                    >
                        遮断
                    </button>
                    {form.errors.cidr ? (
                        <p className="text-sm text-red-400 md:col-span-6">
                            {form.errors.cidr}
                        </p>
                    ) : null}
                </form>
            ) : null}
            <div className="overflow-x-auto rounded-xl border border-zinc-800">
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-zinc-900 text-zinc-400">
                        <tr>
                            <th className="px-3 py-2">CIDR</th>
                            <th className="px-3 py-2">種別</th>
                            <th className="px-3 py-2">対象</th>
                            <th className="px-3 py-2">期限</th>
                            <th className="px-3 py-2">適用</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {blocks.data.map((block) => (
                            <tr
                                key={block.id}
                                className="border-t border-zinc-800"
                            >
                                <td className="px-3 py-2">
                                    {block.cidr}
                                    {block.reason ? (
                                        <span className="block text-xs text-zinc-500">
                                            {block.reason}
                                        </span>
                                    ) : null}
                                </td>
                                <td className="px-3 py-2">{block.source}</td>
                                <td className="px-3 py-2">
                                    {block.node ?? '全ノード'} /{' '}
                                    {block.site ?? '全サイト'}
                                </td>
                                <td className="px-3 py-2">
                                    {block.expires_at ?? '無期限'}
                                </td>
                                <td className="px-3 py-2">
                                    {block.apply_status}
                                    {block.results.map((result) => (
                                        <span
                                            key={`${result.node}-${result.status}`}
                                            className="block text-xs text-zinc-500"
                                        >
                                            {result.node}: {result.status}
                                        </span>
                                    ))}
                                </td>
                                <td className="px-3 py-2">
                                    {isAdmin && block.active ? (
                                        <button
                                            type="button"
                                            className="rounded border border-zinc-700 px-2 py-1 text-xs"
                                            onClick={() => {
                                                if (
                                                    confirm(
                                                        'この遮断を解除しますか？',
                                                    )
                                                ) {
                                                    router.post(
                                                        `/blocks/${block.id}/revoke`,
                                                    );
                                                }
                                            }}
                                        >
                                            解除
                                        </button>
                                    ) : (
                                        <span className="text-zinc-500">
                                            {block.revoked_at
                                                ? '解除済み'
                                                : ''}
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
