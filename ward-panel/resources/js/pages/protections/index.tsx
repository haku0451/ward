import { router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

export default function ProtectionsIndex({
    protections,
}: {
    protections: { id: number; cidr: string; reason: string | null }[];
}) {
    const { auth } = usePage().props;
    const isAdmin = auth.user?.role === 'admin';
    const form = useForm({ cidr: '', reason: '' });

    return (
        <AppLayout title="保護リスト">
            <h1 className="mb-2 text-2xl font-semibold">管理用保護リスト</h1>
            <p className="mb-6 text-sm text-zinc-400">
                ここに入った IP / CIDR
                は手動遮断・自動遮断の対象になりません。誤遮断時は自分の管理 IP
                を先に登録してください。
            </p>
            {isAdmin ? (
                <form
                    className="mb-6 flex flex-wrap gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/protections', {
                            onSuccess: () => form.reset(),
                        });
                    }}
                >
                    <input
                        className="rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm"
                        placeholder="198.51.100.10/32"
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
                    <button
                        type="submit"
                        className="rounded-md bg-emerald-600 px-4 py-2 text-sm"
                        disabled={form.processing}
                    >
                        追加
                    </button>
                    {form.errors.cidr ? (
                        <p className="w-full text-sm text-red-400">
                            {form.errors.cidr}
                        </p>
                    ) : null}
                </form>
            ) : null}
            <ul className="divide-y divide-zinc-800 rounded-xl border border-zinc-800">
                {protections.map((item) => (
                    <li
                        key={item.id}
                        className="flex items-center justify-between px-4 py-3 text-sm"
                    >
                        <span>
                            {item.cidr}
                            {item.reason ? (
                                <span className="ml-2 text-zinc-500">
                                    {item.reason}
                                </span>
                            ) : null}
                        </span>
                        {isAdmin ? (
                            <button
                                type="button"
                                className="text-xs text-zinc-400"
                                onClick={() => {
                                    if (confirm('保護リストから削除しますか？')) {
                                        router.delete(
                                            `/protections/${item.id}`,
                                        );
                                    }
                                }}
                            >
                                削除
                            </button>
                        ) : null}
                    </li>
                ))}
            </ul>
        </AppLayout>
    );
}
