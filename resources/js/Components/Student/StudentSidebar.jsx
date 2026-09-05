import { useRef } from 'react';
import CelikBrand from '../Auth/CelikBrand';
import { Icon } from './DashboardComponents';

export const studentNavigation = [
    { id: 'overview', label: 'Overview', icon: 'overview' },
    { id: 'submit', label: 'Submit Homework', icon: 'submit' },
    { id: 'progress', label: 'My Progress', icon: 'progress' },
    { id: 'work', label: 'Marked Work', icon: 'work' },
    { id: 'practice', label: 'Practice', icon: 'practice' },
];

function SidebarContent({ active, navigate, student, nextDue }) {
    return <div className="flex min-h-full flex-col p-5">
        <div className="px-3 py-4"><CelikBrand /></div>
        <p className="mb-3 mt-8 px-3 text-[10px] font-semibold tracking-[0.18em] text-auth-muted">STUDENT</p>
        <nav aria-label="Student navigation" className="space-y-1">{studentNavigation.map((item) => <button key={item.id} type="button" onClick={() => navigate(item.id)} aria-current={active === item.id ? 'page' : undefined} className={`auth-focus flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-medium ${active === item.id ? 'bg-auth-soft/70 text-auth-green' : 'text-auth-muted hover:bg-auth-page'}`}><Icon name={item.icon} />{item.label}</button>)}</nav>
        <div className="my-7 border-t border-auth-border pt-6"><p className="px-3 text-[10px] font-semibold tracking-[0.18em] text-auth-muted">NEXT DUE</p>{nextDue ? <div className="mt-3 rounded-2xl bg-auth-page p-4"><p className="text-sm font-semibold">{nextDue.title}</p><p className="mt-1 text-xs leading-relaxed text-auth-muted">{nextDue.topic}</p><p className="mt-4 flex items-center gap-2 text-xs"><Icon name="due" className="size-4 text-auth-green" />{nextDue.due}</p><button onClick={() => navigate('due')} className="auth-focus mt-4 flex items-center gap-2 text-xs font-semibold text-auth-green">View Assignment <Icon className="size-4" /></button></div> : <p className="mt-3 px-3 text-sm text-auth-muted">Nothing due soon.</p>}</div>
    </div>;
}

export default function StudentSidebar(props) {
    const drawer = useRef(null);
    return <>
        <aside className="fixed inset-y-0 left-0 hidden w-60 overflow-y-auto border-r border-auth-border bg-white md:block"><SidebarContent {...props} /></aside>
        <button type="button" onClick={() => drawer.current.showModal()} aria-label="Open student navigation" aria-haspopup="dialog" className="auth-focus mb-4 flex items-center gap-2 rounded-xl border border-auth-border bg-white px-3 py-2 text-sm md:hidden"><Icon name="menu" />Menu</button>
        <dialog ref={drawer} aria-label="Student navigation" onClick={(event) => { if (event.target === drawer.current) drawer.current.close(); }} className="fixed inset-y-0 left-0 m-0 h-dvh max-h-none w-72 max-w-[85vw] border-0 bg-white p-0 text-auth-ink backdrop:bg-auth-ink/30"><button autoFocus onClick={() => drawer.current.close()} aria-label="Close student navigation" className="auth-focus absolute right-3 top-3 rounded-lg p-2"><Icon name="close" /></button><SidebarContent {...props} navigate={(view) => { props.navigate(view); drawer.current.close(); }} /></dialog>
    </>;
}
