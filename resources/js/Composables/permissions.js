import { usePage } from "@inertiajs/vue3";

export function usePermission(){
    const hasRole = (name) => usePage().props.auth.user.roles.some(item => item.name === name);
    const hasPermission = (name) => usePage().props.permissions.includes(name);

    return { hasRole, hasPermission };
}