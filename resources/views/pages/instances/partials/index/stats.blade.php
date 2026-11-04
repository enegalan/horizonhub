<div class="grid grid-cols-1 divide-y divide-border sm:grid-cols-2 sm:divide-x lg:grid-cols-4 lg:divide-y-0">
    <x-stat-card label="Services" variant="strip" :value="number_format($services)" />
    <x-stat-card label="Masters" variant="strip" :value="number_format($masters)" />
    <x-stat-card label="Supervisors" variant="strip" :value="number_format($supervisors)" />
    <x-stat-card label="Processes" variant="strip" :value="number_format($processes)" />
</div>
