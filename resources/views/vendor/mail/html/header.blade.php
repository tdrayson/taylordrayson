@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{{-- My face rather than a wordmark: a reply notification is a person writing
     back. The flattened crop, not the transparent one: an email client paints a
     transparent PNG on its own body colour, which breaks it in dark mode. --}}
<img src="{{ url(config('identity.photo')) }}" class="logo" alt="{{ config('identity.name') }}">
</a>
</td>
</tr>
