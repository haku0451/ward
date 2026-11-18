import { Head, Link, router, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';

const nav = [
    { href: '/dashboard', label: 'ダッシュボード' },
    { href: '/events', label: 'イベント' },
    { href: '/nodes', label: 'ノード' },
    { href: '/sites', label: 'サイト' },
    { href: '/blocks', label: '遮断' },
    { href: '/protections', label: '保護' },
    { href: '/policies', label: 'ポリシー' },
    { href: '/audit', label: '監査' },
];

export default function AppLayout({
    title,
    children,
}: PropsWithChildren<{ title: string }>) {
    const { auth, flash, name, errors } = usePage().props;

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen bg-zinc-950 text-zinc-100">
                <header className="border-b border-zinc-800 bg-zinc-900/80">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                        <div className="flex items-center gap-8">
                            <Link href="/dashboard" className="font-semibold text-emerald-400">
                                {name}
                            </Link>
                            <nav className="flex gap-4 text-sm">
                                {nav.map((item) => (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        className="text-zinc-300 hover:text-white"
                                    >
                                        {item.label}
                                    </Link>
                                ))}
                            </nav>
                        </div>
                        <div className="flex items-center gap-4 text-sm">
                            <span className="text-zinc-400">
                                {auth.user?.name} ({auth.user?.role})
                            </span>
                            <button
                                type="button"
                                className="rounded-md border border-zinc-700 px-3 py-1 hover:bg-zinc-800"
                                onClick={() => router.post('/logout')}
                            >
                                ログアウト
                            </button>
                        </div>
                    </div>
                </header>
                <main className="mx-auto max-w-6xl px-6 py-8">
                    {flash.status ? (
                        <p className="mb-4 rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-200">
                            {flash.status}
                        </p>
                    ) : null}
                    {Object.keys(errors).length > 0 ? (
                        <div className="mb-4 rounded-md border border-red-800 bg-red-950 px-4 py-2 text-sm text-red-200">
                            {Object.values(errors).map((message) => (
                                <p key={message}>{message}</p>
                            ))}
                        </div>
                    ) : null}
                    {children}
                </main>
            </div>
        </>
    );
}
