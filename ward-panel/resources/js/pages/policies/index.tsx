import { router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

type Settings = {
    waf_mode: string;
    auto_block_enabled: boolean;
    auto_block_threshold: number;
    auto_block_window_seconds: number;
    auto_block_duration_seconds: number;
};

type Revision = {
    id: number;
    hash: string;
    waf_mode: string;
    signed: boolean;
    rolled_back_from_id: number | null;
    created_at: string | null;
};

export default function PoliciesIndex({
    settings,
    revisions,
    priority,
}: {
    settings: Settings;
    revisions: Revision[];
    priority: string[];
}) {
    const { auth } = usePage().props;
    const isAdmin = auth.user?.role === 'admin';
    const form = useForm({
        waf_mode: settings.waf_mode,
        auto_block_enabled: settings.auto_block_enabled,
        auto_block_threshold: settings.auto_block_threshold,
        auto_block_window_seconds: settings.auto_block_window_seconds,
        auto_block_duration_seconds: settings.auto_block_duration_seconds,
        confirm_auto_block: settings.auto_block_enabled,
    });

    return (
        <AppLayout title="ポリシー">
            <h1 className="mb-2 text-2xl font-semibold">ポリシー</h1>
            <p className="mb-6 text-sm text-zinc-400">
                優先順位: {priority.join(' → ')}
                。自動遮断は初期状態では無効です。
            </p>
            {isAdmin ? (
                <form
                    className="mb-8 grid gap-4 rounded-xl border border-zinc-800 bg-zinc-900 p-5 md:grid-cols-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/policies');
                    }}
                >
                    <label className="text-sm">
                        グローバルモード
                        <select
                            className="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2"
                            value={form.data.waf_mode}
                            onChange={(event) =>
                                form.setData('waf_mode', event.target.value)
                            }
                        >
                            <option value="detection_only">検知のみ</option>
                            <option value="blocking">遮断</option>
                        </select>
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.auto_block_enabled}
                            onChange={(event) =>
                                form.setData(
                                    'auto_block_enabled',
                                    event.target.checked,
                                )
                            }
                        />
                        自動遮断を有効化（共有 NAT を巻き込む可能性があります）
                    </label>
                    <label className="text-sm">
                        閾値（件数）
                        <input
                            className="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2"
                            type="number"
                            value={form.data.auto_block_threshold}
                            onChange={(event) =>
                                form.setData(
                                    'auto_block_threshold',
                                    Number(event.target.value),
                                )
                            }
                        />
                    </label>
                    <label className="text-sm">
                        集計窓（秒）
                        <input
                            className="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2"
                            type="number"
                            value={form.data.auto_block_window_seconds}
                            onChange={(event) =>
                                form.setData(
                                    'auto_block_window_seconds',
                                    Number(event.target.value),
                                )
                            }
                        />
                    </label>
                    <label className="text-sm">
                        自動遮断の有効秒数
                        <input
                            className="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2"
                            type="number"
                            value={form.data.auto_block_duration_seconds}
                            onChange={(event) =>
                                form.setData(
                                    'auto_block_duration_seconds',
                                    Number(event.target.value),
                                )
                            }
                        />
                    </label>
                    {form.data.auto_block_enabled ? (
                        <label className="flex items-center gap-2 text-sm text-amber-200">
                            <input
                                type="checkbox"
                                checked={form.data.confirm_auto_block}
                                onChange={(event) =>
                                    form.setData(
                                        'confirm_auto_block',
                                        event.target.checked,
                                    )
                                }
                            />
                            自動遮断の副作用を理解した
                        </label>
                    ) : null}
                    <button
                        type="submit"
                        className="rounded-md bg-emerald-600 px-4 py-2 text-sm md:col-span-2"
                        disabled={form.processing}
                    >
                        保存して配布
                    </button>
                </form>
            ) : null}
            <h2 className="mb-3 text-lg font-medium">版とロールバック</h2>
            <div className="overflow-x-auto rounded-xl border border-zinc-800">
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-zinc-900 text-zinc-400">
                        <tr>
                            <th className="px-3 py-2">版</th>
                            <th className="px-3 py-2">ハッシュ</th>
                            <th className="px-3 py-2">署名</th>
                            <th className="px-3 py-2">作成</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {revisions.map((revision, index) => (
                            <tr
                                key={revision.id}
                                className="border-t border-zinc-800"
                            >
                                <td className="px-3 py-2">
                                    {revision.id}
                                    {revision.rolled_back_from_id
                                        ? ` (from ${revision.rolled_back_from_id})`
                                        : ''}
                                </td>
                                <td className="max-w-xs truncate px-3 py-2 font-mono text-xs">
                                    {revision.hash}
                                </td>
                                <td className="px-3 py-2">
                                    {revision.signed ? 'signed' : 'unsigned'}
                                </td>
                                <td className="px-3 py-2">
                                    {revision.created_at}
                                </td>
                                <td className="px-3 py-2">
                                    {isAdmin && index !== 0 ? (
                                        <button
                                            type="button"
                                            className="rounded border border-zinc-700 px-2 py-1 text-xs"
                                            onClick={() => {
                                                if (
                                                    confirm(
                                                        `版 ${revision.id} の内容で新しい版を発行します。Agent は新しい版番号だけを受け付けます。`,
                                                    )
                                                ) {
                                                    router.post(
                                                        `/policies/${revision.id}/rollback`,
                                                    );
                                                }
                                            }}
                                        >
                                            この内容に戻す
                                        </button>
                                    ) : null}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
