import { ImageResponse } from "next/og";

export const size = { width: 64, height: 64 };
export const contentType = "image/png";

export default function Icon() {
  return new ImageResponse(
    (
      <div
        style={{
          width: "100%",
          height: "100%",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          background: "#050608",
          borderRadius: 14,
        }}
      >
        <svg width="34" height="34" viewBox="0 0 34 34" fill="none">
          <circle cx="17" cy="5" r="3.4" fill="#c9a876" />
          <circle cx="5" cy="27" r="3.4" fill="#7fa6d9" />
          <circle cx="29" cy="27" r="3.4" fill="#7fa6d9" />
          <path
            d="M17 8.4V17M17 17L7.5 24.5M17 17L26.5 24.5"
            stroke="#f6f5f1"
            strokeWidth="1.6"
            strokeLinecap="round"
          />
        </svg>
      </div>
    ),
    { ...size },
  );
}
