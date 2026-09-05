import { useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';
import { Icon } from './DashboardComponents';

export default function StudentProfileDropdown({ student, isOpen, onOpenChange, onSettings }) {
    const menu = useRef(null);
    const initial = student.name.slice(0, 1);

    useEffect(() => {
        function closeOnOutsideClick(event) {
            if (menu.current && !menu.current.contains(event.target)) {
                onOpenChange(false);
            }
        }

        function closeOnEscape(event) {
            if (event.key === 'Escape') {
                onOpenChange(false);
            }
        }

        document.addEventListener('mousedown', closeOnOutsideClick);
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('mousedown', closeOnOutsideClick);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [onOpenChange]);

    function openSettings() {
        onOpenChange(false);
        onSettings();
    }

    return <div ref={menu} className="relative shrink-0">
        <button type="button" onClick={() => onOpenChange(!isOpen)} aria-expanded={isOpen} aria-haspopup="menu" className="auth-focus flex items-center gap-2 rounded-xl border border-auth-border bg-white p-1.5 pr-2.5 text-left shadow-xs hover:bg-auth-page sm:pr-3">
            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-auth-soft text-sm font-semibold text-auth-green">{initial}</span>
            <span className="hidden min-w-0 sm:block"><span className="block max-w-36 truncate text-sm font-semibold text-auth-ink">{student.name}</span></span>
            <Icon name="chevron" className="size-4 shrink-0 text-auth-muted" />
        </button>

        {isOpen && <div role="menu" aria-label="Student account" className="absolute right-0 z-50 mt-2 w-60 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-auth-border bg-white p-1.5 shadow-lg">
            <div className="flex items-center gap-3 px-3 py-3">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-auth-soft font-semibold text-auth-green">{initial}</span>
                <div className="min-w-0"><p className="truncate text-sm font-semibold text-auth-ink">{student.name}</p><p className="mt-1 truncate text-xs text-auth-muted">{student.classroom}</p></div>
            </div>
            <div className="my-1 border-t border-auth-border" />
            <button type="button" role="menuitem" onClick={openSettings} className="auth-focus flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-auth-muted hover:bg-auth-soft hover:text-auth-green"><Icon name="settings" className="size-4" />Settings</button>
            <button type="button" role="menuitem" onClick={() => router.post('/logout')} className="auth-focus flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-auth-muted hover:bg-red-50 hover:text-red-700"><Icon name="logout" className="size-4" />Logout</button>
        </div>}
    </div>;
}
