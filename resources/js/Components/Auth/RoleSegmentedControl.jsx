export default function RoleSegmentedControl({ value, onChange }) {
    return (
        <div role="group" aria-label="Choose your showcase" className="grid grid-cols-2 gap-1 rounded-2xl bg-auth-page p-1">
            {['student', 'teacher'].map((role) => (
                <button
                    key={role}
                    type="button"
                    aria-pressed={value === role}
                    onClick={() => onChange(role)}
                    className={`auth-focus rounded-xl px-4 py-2 text-sm font-medium capitalize transition-colors motion-reduce:transition-none ${value === role ? 'bg-white text-auth-ink shadow-sm' : 'text-auth-muted hover:text-auth-green'}`}
                >
                    {role}
                </button>
            ))}
        </div>
    );
}
