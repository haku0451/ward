import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';

type NodeRow = {
    id: number;
    uuid: string;
    name: string;
    health: string;
    hostname: string | null;
    agent_version: string | null;
    config_version: number | null;
    waf_mode: string | null;
    last_seen_at: string | null;
};

export default function NodesIndex({
    nodes,
    plainToken,
    agentYaml,
}: {
    nodes: NodeRow[];
    plainToken: string | null;
    agentYaml: string | null;
}) {
    const { auth, flash } = usePage().props;
    const isAdmin = auth.user?.role === 'admin';
    const createForm = useForm({
        name: '',
        hostname: '',
        waf_mode: '',
    });
    const yaml = agentYaml ?? flash.agent_yaml;

    return (
        <AppLayout title="ノード">
            <h1 className="mb-2 text-2xl font-semibold">ノード</h1>
            <p className="mb-6 text-sm text-zinc-400">
                ノードを作成すると Agent 用の{' '}
                <code className="font-mono">agent.yaml</code>{' '}
                が生成されます。コピーして{' '}
                <code className="font-mono">nano configs/agent.yaml</code>{' '}
                に貼り付けて起動してください。
            </p>
            {yaml ? <AgentYamlCard yaml={yaml} token={plainToken ?? flash.plain_token} /> : null}
            {isAdmin ? (
                <form
                    className="mb-6 grid gap-3 rounded-xl border border-zinc-800 bg-zinc-900 p-4 md:grid-cols-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        createForm.post('/nodes', {
                            onSuccess: () => createForm.reset(),
                        });
                    }}
                >
                    <label className="text-sm">
                        ノード名
                        <input
                            className="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm"
                            placeholder="例: edge-1"
                            required
                            value={createForm.data.name}
                            onChange={(event) =>
                                createForm.setData('name', event.target.value)
                            }
                        />
                        {createForm.errors.name ? (
                            <span className="mt-1 block text-xs text-red-400">
                                {createForm.errors.name}
                            </span>
                        ) : null}
                    </label>
                    <label className="text-sm">
                        ホスト名（任意）
                        <input
                            className="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm"
                            placeholder="waf-1.example.test"
                            value={createForm.data.hostname}
                            onChange={(event) =>
                                createForm.setData(
                                    'hostname',
                                    event.target.value,
                                )
                            }
                        />
                    </label>
                    <label className="text-sm">
                        WAF モード
                        <select
                            className="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm"
                            value={createForm.data.waf_mode}
                            onChange={(event) =>
                                createForm.setData(
                                    'waf_mode',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">グローバルを継承</option>
                            <option value="detection_only">検知のみ</option>
                            <option value="blocking">遮断</option>
                        </select>
                    </label>
                    <div className="flex items-end">
                        <button
                            type="submit"
                            className="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm disabled:opacity-50"
                            disabled={createForm.processing}
                        >
                            {createForm.processing ? '作成中…' : '作成'}
                        </button>
                    </div>
                </form>
            ) : null}
            <div className="overflow-x-auto rounded-xl border border-zinc-800">
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-zinc-900 text-zinc-400">
                        <tr>
                            <th className="px-3 py-2">名前</th>
                            <th className="px-3 py-2">状態</th>
                            <th className="px-3 py-2">ホスト名</th>
                            <th className="px-3 py-2">バージョン</th>
                            <th className="px-3 py-2">設定</th>
                            <th className="px-3 py-2">モード</th>
                            <th className="px-3 py-2">最終通信</th>
                            {isAdmin ? <th className="px-3 py-2" /> : null}
                        </tr>
                    </thead>
                    <tbody>
                        {nodes.length === 0 ? (
                            <tr>
                                <td
                                    className="px-3 py-6 text-zinc-500"
                                    colSpan={isAdmin ? 8 : 7}
                                >
                                    まだノードがありません。上のフォームから作成してください。
                                </td>
                            </tr>
                        ) : (
                            nodes.map((node) => (
                                <tr
                                    key={node.id}
                                    className="border-t border-zinc-800"
                                >
                                    <td className="px-3 py-2">
                                        {node.name}
                                        <span className="block text-xs text-zinc-500">
                                            {node.uuid}
                                        </span>
                                    </td>
                                    <td className="px-3 py-2">{node.health}</td>
                                    <td className="px-3 py-2">
                                        {node.hostname ?? '-'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {node.agent_version ?? '-'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {node.config_version ?? '-'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {isAdmin ? (
                                            <NodeModeSelect node={node} />
                                        ) : (
                                            node.waf_mode ?? '継承'
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        {node.last_seen_at ?? '-'}
                                    </td>
                                    {isAdmin ? (
                                        <td className="px-3 py-2">
                                            <div className="flex flex-col gap-1">
                                                <ConfigButton nodeId={node.id} />
                                                <RotateButton nodeId={node.id} />
                                            </div>
                                        </td>
                                    ) : null}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}

function AgentYamlCard({
    yaml,
    token,
}: {
    yaml: string;
    token: string | null;
}) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(yaml);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            setCopied(false);
        }
    };

    return (
        <div className="mb-6 rounded-md border border-amber-700 bg-amber-950 px-4 py-3 text-sm">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <p className="font-medium text-amber-200">
                    agent.yaml（この画面で一度だけ完全な内容を表示します）
                </p>
                <button
                    type="button"
                    className="rounded border border-amber-600 px-3 py-1 text-xs text-amber-100 hover:bg-amber-900"
                    onClick={() => void copy()}
                >
                    {copied ? 'コピーしました' : 'YAML をコピー'}
                </button>
            </div>
            <p className="mb-2 text-amber-200/80">
                サーバーで{' '}
                <code className="font-mono">nano configs/agent.yaml</code>{' '}
                を開き、下を貼り付けて保存したあと Agent を起動します。
            </p>
            {token ? (
                <p className="mb-2 break-all font-mono text-xs text-amber-100/70">
                    token: {token}
                </p>
            ) : null}
            <textarea
                readOnly
                className="h-72 w-full rounded-md border border-amber-800 bg-zinc-950 p-3 font-mono text-xs text-amber-100"
                value={yaml}
                onFocus={(event) => event.currentTarget.select()}
            />
        </div>
    );
}

function ConfigButton({ nodeId }: { nodeId: number }) {
    const form = useForm({});

    return (
        <button
            type="button"
            className="rounded border border-zinc-700 px-2 py-1 text-xs"
            onClick={() => form.post(`/nodes/${nodeId}/agent-config`)}
        >
            YAML を表示
        </button>
    );
}

function NodeModeSelect({ node }: { node: NodeRow }) {
    const form = useForm({ waf_mode: node.waf_mode ?? '' });

    return (
        <select
            className="rounded border border-zinc-700 bg-zinc-950 px-2 py-1 text-xs"
            value={form.data.waf_mode}
            onChange={(event) => {
                form.setData('waf_mode', event.target.value);
                form.patch(`/nodes/${node.id}`, { preserveScroll: true });
            }}
        >
            <option value="">継承</option>
            <option value="detection_only">検知のみ</option>
            <option value="blocking">遮断</option>
        </select>
    );
}

function RotateButton({ nodeId }: { nodeId: number }) {
    const form = useForm({});

    return (
        <button
            type="button"
            className="rounded border border-zinc-700 px-2 py-1 text-xs"
            onClick={() => {
                if (
                    confirm(
                        'トークンを再発行します。既存の Agent は新しいトークンが必要になります。',
                    )
                ) {
                    form.post(`/nodes/${nodeId}/token`);
                }
            }}
        >
            トークン再発行
        </button>
    );
}
