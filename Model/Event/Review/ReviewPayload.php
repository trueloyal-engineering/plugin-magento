<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\Model\Event\Review;

use Magento\Review\Model\Review;
use TrueLoyal\LoyaltyRewards\Model\Event\Order\Formatter;

/**
 * review_submitted / review_approved: the review, with the product's links and last category
 */
class ReviewPayload
{
    public function __construct(private readonly Formatter $formatter)
    {
    }

    public function build(Review $review, int|string $productId): array
    {
        $data = $review->toArray();
        $links = $this->formatter->productLinks($productId);
        $data['product_url'] = $links['product_url'];
        $data['product_image_url'] = $links['product_image_url'];
        $data['category_name'] = $this->formatter->lastCategoryName($productId);
        return $data;
    }
}
