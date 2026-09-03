import { ImageResponse } from "next/og";
import { site } from "@/lib/data/site";

export const alt = `${site.name} — Your Business. Built for the Digital World.`;
export const size = { width: 1200, height: 630 };
export const contentType = "image/png";

export default async function Image() {
  return new ImageResponse(
    (
      <div
        style={{
          width: "100%",
          height: "100%",
          display: "flex",
          flexDirection: "column",
          justifyContent: "space-between",
          padding: "80px",
          background: "#050608",
          backgroundImage:
            "radial-gradient(circle at 15% 15%, rgba(201,168,118,0.16), transparent 45%), radial-gradient(circle at 85% 85%, rgba(127,166,217,0.14), transparent 45%)",
        }}
      >
        <div style={{ display: "flex", alignItems: "center", gap: 14 }}>
          <div
            style={{
              width: 14,
              height: 14,
              borderRadius: 999,
              background: "#c9a876",
              display: "flex",
            }}
          />
          <span
            style={{
              fontSize: 30,
              letterSpacing: 6,
              color: "#f6f5f1",
              fontWeight: 600,
            }}
          >
            TECHBISS
          </span>
        </div>

        <div style={{ display: "flex", flexDirection: "column", gap: 22, maxWidth: 980 }}>
          <span
            style={{
              fontSize: 68,
              lineHeight: 1.06,
              color: "#f6f5f1",
              fontWeight: 600,
              letterSpacing: -2,
            }}
          >
            Your Business. Built for the Digital World.
          </span>
          <span style={{ fontSize: 26, color: "#a7abb8", maxWidth: 820 }}>
            Websites, apps, hosting, security, email and payments — one
            digital ecosystem for your business.
          </span>
        </div>

        <div
          style={{
            display: "flex",
            gap: 10,
            fontSize: 20,
            color: "#6b6f7c",
            letterSpacing: 2,
          }}
        >
          <span>WEBSITES</span>
          <span style={{ color: "#c9a876" }}>·</span>
          <span>APPS</span>
          <span style={{ color: "#c9a876" }}>·</span>
          <span>HOSTING</span>
          <span style={{ color: "#c9a876" }}>·</span>
          <span>SECURITY</span>
          <span style={{ color: "#c9a876" }}>·</span>
          <span>PAYMENTS</span>
        </div>
      </div>
    ),
    { ...size },
  );
}
