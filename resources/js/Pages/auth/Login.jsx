import { Head, Link, useForm } from '@inertiajs/react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
            <Head title="Log in" />
            <div className="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                <h1 className="text-xl font-semibold">Celik</h1>
                <p className="mt-1 text-sm text-slate-500">Adaptive practice &amp; class-wide progress tracking.</p>

                <form onSubmit={submit} className="mt-6 space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-slate-700">Email</label>
                        <input
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
                        />
                        {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700">Password</label>
                        <input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
                        />
                    </div>
                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-60"
                    >
                        Log in
                    </button>
                </form>

                <p className="mt-6 text-center text-xs text-slate-500">
                    Need an account?{' '}
                    <Link href="/register" className="font-medium text-indigo-600 hover:text-indigo-500">
                        Register
                    </Link>
                </p>

                <div className="mt-6 rounded-md bg-slate-50 p-3 text-xs text-slate-500">
                    <p className="font-medium text-slate-600">Demo accounts (password: password)</p>
                    <p>Teacher &mdash; teacher@example.com</p>
                    <p>Student &mdash; student1@example.com</p>
                </div>
            </div>
        </div>
    );
}
