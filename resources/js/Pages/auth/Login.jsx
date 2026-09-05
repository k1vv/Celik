import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AuthPageShell from '../../Components/Auth/AuthPageShell';
import AuthShowcasePanel from '../../Components/Auth/AuthShowcasePanel';
import useShowcase from '../../Components/Auth/useShowcase';
import { showcaseSlides } from '../../Components/Auth/showcaseSlides';
import PasswordField from '../../Components/Auth/PasswordField';
import RoleSegmentedControl from '../../Components/Auth/RoleSegmentedControl';

export default function Login() {
    // This selection changes the presentation only; the account determines its role.
    const [selectedRole, setSelectedRole] = useState('student');
    const showcase = useShowcase();

    const handleRoleChange = (role) => {
        setSelectedRole(role);
        const slideId = role === 'student' ? 'adaptive-practice' : 'classroom-insights';
        const slideIndex = showcaseSlides.findIndex((slide) => slide.id === slideId);
        showcase.goToSlide(slideIndex < 0 ? 0 : slideIndex);
    };
    const { data, setData, post, processing, errors } = useForm({ email: '', password: '' });

    const submit = (event) => {
        event.preventDefault();
        post('/login');
    };

    return (
        <AuthPageShell>
            <Head title="Log in" />
            <AuthShowcasePanel activeSlide={showcase.activeSlide} onSlideChange={showcase.goToSlide} paused={showcase.paused} onPauseChange={showcase.setPaused} hovered={showcase.hovered} onHoverChange={showcase.setHovered} />
            <LoginFormPanel>
                <h1 className="text-3xl font-semibold tracking-tight">Welcome back</h1>
                <p className="auth-intro mt-2 text-sm leading-relaxed text-auth-muted">Sign in to hand in homework, or to review your class.</p>
                <form onSubmit={submit} className="auth-form mt-4">
                    <RoleSegmentedControl value={selectedRole} onChange={handleRoleChange} />
                    <div className="mt-3">
                        <label htmlFor="email" className="mb-2 block text-sm font-medium">Email</label>
                        <input id="email" name="email" type="email" autoComplete="username" autoCapitalize="none" spellCheck={false} value={data.email} onChange={(event) => setData('email', event.target.value)} placeholder={selectedRole === 'student' ? 'student@school.edu' : 'teacher@school.edu'} aria-invalid={Boolean(errors.email)} aria-describedby={errors.email ? 'email-error' : undefined} className="auth-input" />
                        <p id="email-error" aria-live="polite" className="mt-1 min-h-4 text-xs text-red-700">{errors.email}</p>
                    </div>
                    <div className="mt-3"><PasswordField value={data.password} onChange={(event) => setData('password', event.target.value)} error={errors.password} /></div>
                    <div className="mt-2 text-xs text-auth-muted">
                        <label htmlFor="remember" className="flex items-center gap-2"><input id="remember" type="checkbox" checked disabled aria-describedby="remember-note" className="size-4 accent-auth-green" />Keep me signed in</label>
                        <p id="remember-note" className="mt-1.5">Always enabled for Celik accounts.</p>
                    </div>
                    <button type="submit" disabled={processing} className="auth-focus mt-3 flex min-h-12 w-full items-center justify-between rounded-xl bg-auth-green px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-auth-ink active:bg-auth-ink disabled:cursor-wait disabled:opacity-60 motion-reduce:transition-none">
                        <span>{processing ? 'Signing in...' : 'Sign in'}</span>
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" className="size-5"><path d="M4 12h15m-6-6 6 6-6 6" /></svg>
                    </button>
                </form>
                <div className="auth-divider my-3 flex items-center gap-4" aria-hidden="true"><div className="h-px flex-1 bg-auth-border" /><span className="text-xs text-auth-muted">or</span><div className="h-px flex-1 bg-auth-border" /></div>
                <button type="button" disabled aria-describedby="school-id-note" className="auth-school-button flex min-h-11 w-full items-center justify-center gap-3 rounded-xl border border-auth-border bg-auth-page/50 px-3 py-3 text-sm font-medium text-auth-muted disabled:cursor-not-allowed">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" className="size-5"><rect x="3" y="5" width="18" height="14" rx="3" /><circle cx="8" cy="10" r="1.5" /><path d="M5.5 16c0-3 5-3 5 0M14 10h4m-4 4h4" /></svg>
                    Continue with school ID
                </button>
                <p id="school-id-note" className="mt-2 text-center text-xs text-auth-muted">School ID sign-in is not available yet.</p>
                <p className="auth-register mt-3 text-center text-xs leading-relaxed text-auth-muted">New to Celik?{' '}<Link href="/register" className="auth-focus rounded font-semibold text-auth-green underline underline-offset-4">Create an account.</Link></p>
                <details className="auth-demo relative mt-3 text-xs text-auth-muted"><summary className="auth-focus cursor-pointer rounded text-center">Try a demo account</summary><div className="absolute bottom-full left-0 z-20 mb-2 w-full rounded-xl border border-auth-border bg-auth-page p-3 leading-relaxed shadow-lg"><p>Password: password</p><p>Teacher: teacher@example.com</p><p>Student: student1@example.com</p></div></details>
            </LoginFormPanel>
        </AuthPageShell>
    );
}

function LoginFormPanel({ children }) {
    return <section className="auth-form-panel flex h-full min-h-0 min-w-0 items-center justify-center overflow-hidden bg-white px-6 md:px-10 lg:px-12"><div className="auth-form-content w-full max-w-[420px]">{children}</div></section>;
}
