import { NextResponse } from "next/server";
import { z } from "zod";

const contactSchema = z.object({
  name: z.string().trim().min(2, "Please enter your full name.").max(120),
  email: z.string().trim().email("Please enter a valid email address."),
  company: z.string().trim().max(160).optional().or(z.literal("")),
  businessType: z.string().trim().max(80).optional().or(z.literal("")),
  budget: z.string().trim().max(80).optional().or(z.literal("")),
  message: z
    .string()
    .trim()
    .min(20, "Tell us a little more — at least 20 characters.")
    .max(4000),
  website: z.string().max(0).optional().or(z.literal("")),
});

export async function POST(request: Request) {
  let body: unknown;

  try {
    body = await request.json();
  } catch {
    return NextResponse.json(
      { ok: false, message: "Invalid request body." },
      { status: 400 },
    );
  }

  const parsed = contactSchema.safeParse(body);

  if (!parsed.success) {
    const fieldErrors = parsed.error.flatten().fieldErrors;
    return NextResponse.json(
      { ok: false, message: "Please check the form and try again.", fieldErrors },
      { status: 422 },
    );
  }

  if (parsed.data.website) {
    return NextResponse.json({ ok: true });
  }

  // Inquiry validated. Wire this up to your email/CRM provider of choice
  // (e.g. Resend, Postmark, HubSpot) to deliver it to the sales team.
  console.info("[contact] new inquiry", {
    name: parsed.data.name,
    email: parsed.data.email,
    company: parsed.data.company,
    businessType: parsed.data.businessType,
    budget: parsed.data.budget,
  });

  return NextResponse.json({ ok: true });
}
