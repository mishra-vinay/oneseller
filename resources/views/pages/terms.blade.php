<x-pages-layout :title="__('Terms of use')">
    <p>{{ $siteName }} is an online marketplace operated on talkforweb.in. Independent sellers list and sell their own products; {{ $siteName }} provides the platform, takes payments and passes orders to sellers.</p>
    <h2>Accounts</h2>
    <p>You are responsible for keeping your password safe and for activity on your account. We may suspend accounts that break these terms.</p>
    <h2>Orders and prices</h2>
    <p>Prices are set by sellers, are in Indian Rupees and include GST. An order containing items from several sellers is shipped as separate parcels, each fulfilled by that seller.</p>
    <h2>Sellers</h2>
    <p>Sellers are responsible for the accuracy of their listings, the quality of their products and dispatching orders on time. {{ $siteName }} charges sellers a commission on each sale.</p>
    <h2>Contact</h2>
    <p>Questions: {{ app(\App\Support\Settings::class)->get('support_email') ?: 'see the contact address on this site' }}.</p>
</x-pages-layout>
