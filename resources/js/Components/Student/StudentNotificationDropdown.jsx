import { useEffect, useRef } from 'react';
import { Icon } from './DashboardComponents';

export default function StudentNotificationDropdown({ assignments, isOpen, onOpenChange, onViewDue }) {
    const menu = useRef(null);

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

    return <div ref={menu} className="relative shrink-0">
        <button type="button" onClick={() => onOpenChange(!isOpen)} aria-expanded={isOpen} aria-haspopup="menu" aria-label={`Notifications${assignments.length ? `, ${assignments.length} upcoming` : ''}`} className="auth-focus relative flex size-10 items-center justify-center rounded-xl border border-auth-border bg-white text-auth-green shadow-xs hover:bg-auth-page">
            <Icon name="bell" className="size-5" />
            {assignments.length > 0 && <span className="absolute -right-1 -top-1 flex size-4 items-center justify-center rounded-full bg-[#d59142] text-[9px] font-bold text-white">{assignments.length}</span>}
        </button>

        {isOpen && <NotificationMenuContent assignments={assignments} onViewDue={onViewDue} />}
    </div>;
}

export function NotificationMenuContent({ assignments, onViewDue }) {
    return <div role="menu" aria-label="Notifications" className="absolute right-0 z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-auth-border bg-white p-1.5 shadow-lg sm:w-88">
        <div className="flex items-center justify-between px-3 py-3"><div><p className="text-sm font-semibold text-auth-ink">Notifications</p><p className="mt-0.5 text-xs text-auth-muted">{assignments.length ? `${assignments.length} upcoming` : 'All caught up'}</p></div></div>
        <div className="border-t border-auth-border" />
        {assignments.length ? <div className="py-1">{assignments.map((assignment) => <button key={assignment.id} type="button" role="menuitem" onClick={onViewDue} className="auth-focus block w-full rounded-xl px-3 py-3 text-left hover:bg-auth-page"><div className="flex items-start justify-between gap-3"><div className="min-w-0"><p className="truncate text-sm font-semibold text-auth-ink">{assignment.title}</p><p className="mt-1 truncate text-xs text-auth-muted">{assignment.topic || assignment.subject}</p><p className="mt-2 text-xs font-medium text-auth-green">Due {assignment.due}{assignment.dueTime ? ` · ${assignment.dueTime}` : ''}</p></div><span className="mt-1 rounded-full bg-[#fff2d7] px-2 py-1 text-[10px] font-semibold text-[#8b6318]">Due soon</span></div><span className="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-auth-green">View assignment <Icon className="size-3" /></span></button>)}</div> : <div className="px-3 py-6 text-center"><p className="text-sm font-semibold text-auth-ink">You're all caught up.</p><p className="mt-1 text-xs text-auth-muted">No upcoming homework right now.</p></div>}
        <div className="border-t border-auth-border pt-1"><button type="button" role="menuitem" onClick={onViewDue} className="auth-focus flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-sm font-semibold text-auth-green hover:bg-auth-soft">View all due work <Icon className="size-4" /></button></div>
    </div>;
}
