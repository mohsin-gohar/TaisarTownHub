New matches for "{{ $searchName }}" — TaisarTownHub

Search criteria: {{ $summary }}

@foreach ($items as $item)
{{ $item['title'] }}
{{ $item['location'] }} | {{ $item['type'] }} | {{ $item['size'] }} Sq. Yd.
{{ $item['price'] }}
{{ $item['url'] }}

@endforeach

You received this alert because you saved a search on TaisarTownHub.
Manage or delete it from: {{ $manageUrl }}
