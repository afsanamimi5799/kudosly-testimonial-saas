<?php

namespace App\Http\Controllers;

use App\Billing\WebhookHandler;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receives the Stripe events.
 *
 * The endpoint is reachable without a session, because Stripe posts to it
 * from outside. The signature is checked before anything in the payload is
 * read, so an event that was not sent by Stripe can never change a plan.
 */
class StripeWebhookController extends Controller
{
    /**
     * Handle an incoming Stripe event.
     *
     * A payload whose signature does not match is rejected with a bad
     * request. Stripe treats that as a failure and retries, which is the
     * right answer for a request that was not really from Stripe.
     */
    public function __invoke(Request $request, WebhookHandler $handler): Response
    {
        $secret = config('cashier.webhook.secret');

        if (! is_string($secret) || $secret === '') {
            // Without a secret no event can be verified, so nothing may be
            // trusted. The events are refused rather than guessed at.
            return new Response('Webhook is not configured', 503);
        }

        try {
            $event = Webhook::constructEvent(
                $this->payload($request),
                (string) $request->header('Stripe-Signature', ''),
                $secret,
                (int) config('cashier.webhook.tolerance', 300),
            );
        } catch (SignatureVerificationException) {
            return new Response('Invalid signature', 400);
        } catch (\UnexpectedValueException) {
            return new Response('Invalid payload', 400);
        }

        $handler->handle($event->toArray());

        return new Response('Webhook Handled', 200);
    }

    /**
     * The exact bytes Stripe signed.
     *
     * The signature covers the payload as it was sent, so the raw body is
     * what has to be verified. When the body has already been read by
     * something upstream, the request keeps a copy of it and that copy is
     * used instead, so the signature is still checked against the bytes
     * that arrived rather than against a re-encoded payload.
     */
    private function payload(Request $request): string
    {
        $content = $request->getContent();

        if ($content !== '' && $content !== false) {
            return $content;
        }

        $cached = $request->getCachedContent();

        return is_string($cached) ? $cached : '';
    }
}
