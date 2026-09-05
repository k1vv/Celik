import { useState } from 'react';

export default function PasswordField({ value, onChange, error }) {
    const [showPassword, setShowPassword] = useState(false);

    return (
        <div>
            <label htmlFor="password" className="mb-2 block text-sm font-medium">Password</label>
            <div className="relative">
                <input
                    id="password"
                    name="password"
                    type={showPassword ? 'text' : 'password'}
                    autoComplete="current-password"
                    value={value}
                    onChange={onChange}
                    aria-invalid={Boolean(error)}
                    aria-describedby={error ? 'password-error' : undefined}
                    className="auth-input pr-20"
                />
                <button type="button" onClick={() => setShowPassword(!showPassword)} aria-label={showPassword ? 'Hide password' : 'Show password'} aria-pressed={showPassword} className="auth-focus absolute inset-y-2 right-2 rounded-lg px-3 text-xs font-semibold text-auth-green">
                    {showPassword ? 'Hide' : 'Show'}
                </button>
            </div>
            <p id="password-error" aria-live="polite" className="mt-1 min-h-4 text-xs text-red-700">{error}</p>
        </div>
    );
}
