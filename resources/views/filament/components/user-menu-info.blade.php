@php
    $user = auth()->user();
    $name = $user?->name ?? 'Fábio Oliveira';
    $role = $user?->role?->value === 'super_admin' ? 'Super admin' : ($user?->role?->getLabel() ?? 'Usuário');
@endphp

<div class="hidden sm:flex flex-col items-start leading-tight mr-1 text-left select-none pointer-events-none">
    <span style="font-size: 14px; font-weight: 600; color: #201820; line-height: 1.25;">{{ $name }}</span>
    <span style="font-size: 12px; color: #5E585E; line-height: 1.25;">{{ $role }}</span>
</div>
