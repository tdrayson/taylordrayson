@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{{-- My face rather than a wordmark: the name is in the greeting, the footer and
     the address, and a reply notification is a person writing back. --}}
<img src="{{ url(config('feed.author_photo')) }}" class="logo" alt="{{ config('feed.author_name') }}">
</a>
</td>
</tr>
