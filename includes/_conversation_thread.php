<?php
/**
 * Shared conversation thread + reply form partial.
 *
 * Expected variables:
 * - $thread: list of message rows
 * - $viewerId: int
 * - $otherPartyLabel: string (e.g. "Lister" or buyer name)
 * - $canReply: bool
 * - $formAction: string path for POST
 * - $propertyId: int
 * - $buyerId: int|null (required for lister roles)
 */
declare(strict_types=1);

if (!isset($thread, $viewerId, $otherPartyLabel, $canReply, $formAction, $propertyId)) {
    return;
}
?>
<section class="message-thread-panel summary-panel" aria-labelledby="thread-heading">
    <h2 id="thread-heading" class="summary-title">Conversation</h2>

    <?php if ($thread === []): ?>
        <p class="empty-state mb-0">No messages yet. Send the first message below.</p>
    <?php else: ?>
        <div class="message-thread" role="log" aria-live="polite">
            <?php foreach ($thread as $message): ?>
                <?php
                $isOutgoing = (int) ($message['sender_id'] ?? 0) === $viewerId;
                $bubbleClass = $isOutgoing ? 'message-bubble is-outgoing' : 'message-bubble is-incoming';
                $senderLabel = $isOutgoing ? 'You' : $otherPartyLabel;
                ?>
                <article class="<?php echo e($bubbleClass); ?>">
                    <header class="message-meta">
                        <span class="message-sender"><?php echo e($senderLabel); ?></span>
                        <time class="message-time" datetime="<?php echo e((string) ($message['created_at'] ?? '')); ?>">
                            <?php echo e(message_format_datetime($message['created_at'] ?? null)); ?>
                        </time>
                    </header>
                    <div class="message-body"><?php echo nl2br(e((string) ($message['message_text'] ?? ''))); ?></div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($canReply): ?>
    <section class="summary-panel message-compose-panel" aria-labelledby="compose-heading">
        <h2 id="compose-heading" class="summary-title">Reply</h2>
        <form method="post" action="<?php echo e(url($formAction)); ?>" class="message-compose-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="send_message">
            <input type="hidden" name="property_id" value="<?php echo e((string) $propertyId); ?>">
            <?php if (isset($buyerId) && $buyerId !== null): ?>
                <input type="hidden" name="buyer_id" value="<?php echo e((string) $buyerId); ?>">
            <?php endif; ?>
            <div class="mb-3">
                <label for="message_text" class="form-label">Your message</label>
                <textarea
                    class="form-control"
                    id="message_text"
                    name="message_text"
                    rows="4"
                    maxlength="<?php echo e((string) MESSAGE_MAX_LENGTH); ?>"
                    required
                ></textarea>
                <div class="form-text">Up to <?php echo e((string) MESSAGE_MAX_LENGTH); ?> characters.</div>
            </div>
            <button type="submit" class="btn btn-auth">Send message</button>
        </form>
    </section>
<?php endif; ?>
