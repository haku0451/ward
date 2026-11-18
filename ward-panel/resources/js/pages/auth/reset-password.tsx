import { useForm } from '@inertiajs/react';
import GuestLayout from '@/layouts/guest-layout';

export default function ResetPassword({
    email,
    token,
}: {
    email: string;
    token: string;
}) {
    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    return (
        <GuestLayout title="新しいパスワード">
            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/reset-password');
                }}
            >
                <label className="block text-sm">
                    メールアドレス
                    <input
                        className="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2"
                        type="email"
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                        required
                    />
                </label>
                <label className="block text-sm">
                    新しいパスワード
                    <input
                        className="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2"
                        type="password"
                        value={form.data.password}
                        onChange={(event) =>
                            form.setData('password', event.target.value)
                        }
                        required
                    />
                    {form.errors.password ? (
                        <span className="mt-1 block text-red-400">
                            {form.errors.password}
                        </span>
                    ) : null}
                </label>
                <label className="block text-sm">
                    パスワード（確認）
                    <input
                        className="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2"
                        type="password"
                        value={form.data.password_confirmation}
                        onChange={(event) =>
                            form.setData(
                                'password_confirmation',
                                event.target.value,
                            )
                        }
                        required
                    />
                </label>
                <button
                    type="submit"
                    className="w-full rounded-md bg-emerald-600 px-4 py-2 font-medium hover:bg-emerald-500 disabled:opacity-50"
                    disabled={form.processing}
                >
                    パスワードを更新
                </button>
            </form>
        </GuestLayout>
    );
}
