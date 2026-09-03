"use client";

import * as React from "react";
import type { Product } from "@/lib/types";

export type CartItem = { product: Product; qty: number };

type CartContextValue = {
  items: CartItem[];
  count: number;
  subtotal: number;
  add: (product: Product) => void;
  remove: (slug: string) => void;
  clear: () => void;
  has: (slug: string) => boolean;
};

const CartContext = React.createContext<CartContextValue | null>(null);

const STORAGE_KEY = "techbiss.cart.items";

export function CartProvider({ children }: { children: React.ReactNode }) {
  const [items, setItems] = React.useState<CartItem[]>([]);
  const [hydrated, setHydrated] = React.useState(false);

  React.useEffect(() => {
    try {
      const raw = window.localStorage.getItem(STORAGE_KEY);
      if (raw) setItems(JSON.parse(raw));
    } catch {
      // ignore
    }
    setHydrated(true);
  }, []);

  React.useEffect(() => {
    if (!hydrated) return;
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    } catch {
      // ignore
    }
  }, [items, hydrated]);

  const add = React.useCallback((product: Product) => {
    setItems((prev) => {
      if (prev.some((item) => item.product.slug === product.slug)) return prev;
      return [...prev, { product, qty: 1 }];
    });
  }, []);

  const remove = React.useCallback((slug: string) => {
    setItems((prev) => prev.filter((item) => item.product.slug !== slug));
  }, []);

  const clear = React.useCallback(() => setItems([]), []);

  const has = React.useCallback((slug: string) => items.some((item) => item.product.slug === slug), [items]);

  const count = items.length;
  const subtotal = items.reduce((sum, item) => sum + item.product.price * item.qty, 0);

  return (
    <CartContext.Provider value={{ items, count, subtotal, add, remove, clear, has }}>
      {children}
    </CartContext.Provider>
  );
}

export function useCart() {
  const ctx = React.useContext(CartContext);
  if (!ctx) throw new Error("useCart must be used within CartProvider");
  return ctx;
}
