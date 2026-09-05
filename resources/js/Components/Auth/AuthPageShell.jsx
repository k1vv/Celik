export default function AuthPageShell({ children }) {
    return (
        <div className="auth-page h-dvh w-full overflow-hidden bg-auth-page p-3 text-auth-ink lg:p-4">
            <main className="auth-grid mx-auto grid h-full min-h-0 max-w-[1600px] overflow-hidden rounded-[28px] border border-auth-border bg-white shadow-sm lg:grid-cols-[1.05fr_0.95fr]">
                {children}
            </main>
        </div>
    );
}
