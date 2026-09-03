"use client";

import { useRouter } from "next/navigation";
import { Check, Heart, ShoppingCart } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { useCart } from "@/lib/cart-context";
import type { Product } from "@/lib/types";

export function ProductActions({ product }: { product: Product }) {
  const { add, has } = useCart();
  const router = useRouter();
  const inCart = has(product.slug);

  return (
    <div className="flex flex-col gap-3 sm:flex-row lg:flex-col">
      <Button
        variant="secondary"
        size="lg"
        className="flex-1"
        onClick={() => router.push(`/marketplace/checkout?product=${product.slug}`)}
      >
        Buy Now
      </Button>
      <Button
        variant="outline"
        size="lg"
        className="flex-1"
        icon={inCart ? <Check className="h-4 w-4" /> : <ShoppingCart className="h-4 w-4" />}
        iconPosition="left"
        onClick={() => add(product)}
      >
        {inCart ? "Added to Cart" : "Add to Cart"}
      </Button>
      <Button variant="ghost" size="lg" icon={<Heart className="h-4 w-4" />} iconPosition="left">
        Save
      </Button>
    </div>
  );
}
