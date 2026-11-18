import { Link, useForm, usePage } from '@inertiajs/react';
import GuestLayout from '@/layouts/guest-layout';

export default function ForgotPassword() {
    const { flash } = usePage().props;
    const form = useForm({
        email: '',
    });

    return (
        <GuestLayout title="パスワード再設定">
            {flash.status ? (
                <p className="mb-4 text-sm text-emerald-300">{flash.status}</p>
            ) : null}
            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/forgot-password');
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
                    {form.errors.email ? (
                        <span className="mt-1 block text-red-400">
                            {form.errors.email}
                        </span>
                    ) : null}
                </label>
                <button
                    type="submit"
                    className="w-full rounded-md bg-emerald-600 px-4 py-2 font-medium hover:bg-emerald-500 disabled:opacity-50"
                    disabled={form.processing}
                >
                    再設定リンクを送る
                </button>
            </form>
            <p className="mt-4 text-sm text-zinc-400">
                <Link href="/login" className="underline">
                    ログインに戻る
                </Link>
            </p>
        </GuestLayout>
    );
}
