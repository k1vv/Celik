import { Link, usePage, router } from '@inertiajs/react';

export default function Layout({ children }) {
    const { auth } = usePage().props;

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900">
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                    <Link href="/" className="text-lg font-semibold tracking-tight">
                        Celik
                    </Link>
                    {auth?.user && (
                        <div className="flex items-center gap-4 text-sm text-slate-600">
                            <span>
                                {auth.user.name} <span className="text-slate-400">&middot; {auth.user.role}</span>
                            </span>
                            <button
                                onClick={() => router.post('/logout')}
                                className="rounded-md border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-100"
                            >
                                Log out
                            </button>
                        </div>
                    )}
                </div>
            </header>
            <main className="mx-auto max-w-6xl px-6 py-8">{children}</main>
        </div>
    );
}
