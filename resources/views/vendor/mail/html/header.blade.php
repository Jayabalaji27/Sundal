{{-- Overrides Laravel's notification email header (verify email, password reset, alerts) to show
     the brand logo instead of the app name as text. A URL rather than an embedded image: these
     emails are rendered as markdown components, where the message isn't available to embed into.
     The rest of Laravel's mail theme is unchanged. --}}
@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ \App\Support\BrandLogo::url() }}" alt="{{ trim(strip_tags($slot)) ?: config('app.name') }}" width="160" style="width: 160px; max-width: 100%; height: auto; border: none;">
</a>
</td>
</tr>
