import { Head } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';

export default function GuestLayout({
    title,
    children,
}: PropsWithChildren<{ title: string }>) {
    return (
        <>
            <Head title={title} />
            <div className="flex min-h-screen items-center justify-center bg-zinc-950 px-4 text-zinc-100">
                <div className="w-full max-w-md rounded-2xl border border-zinc-800 bg-zinc-900 p-8 shadow-xl">
                    <p className="mb-1 text-sm font-medium tracking-wide text-emerald-400">
                        Ward
                    </p>
                    <h1 className="mb-6 text-xl font-semibold">{title}</h1>
                    {children}
                </div>
            </div>
        </>
    );
}
