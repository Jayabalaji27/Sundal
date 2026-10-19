/**
 * "Create" links (header Create menu, command palette, project pages) open a
 * list page with ?action=create. Returns true once per visit when that intent is
 * present and removes it from the address, so a reload doesn't reopen the form.
 */
export function consumeCreateIntent(): boolean {
    const url = new URL(window.location.href);
    if (url.searchParams.get('action') !== 'create') return false;
    url.searchParams.delete('action');
    window.history.replaceState(window.history.state, '', url.toString());
    return true;
}
