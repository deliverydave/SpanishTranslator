export function digitsPhone(phone: string): string {
  const trimmed = phone.trim();
  const plus = trimmed.startsWith("+");
  const digits = trimmed.replace(/\D/g, "");
  return plus ? `+${digits}` : digits;
}

export function smsHref(phone: string, body: string): string {
  const number = digitsPhone(phone);
  const encoded = encodeURIComponent(body);
  const isiOS =
    typeof navigator !== "undefined" &&
    /iPad|iPhone|iPod/i.test(navigator.userAgent);
  if (!number) {
    return isiOS ? `sms:&body=${encoded}` : `sms:?body=${encoded}`;
  }
  return isiOS ? `sms:${number}&body=${encoded}` : `sms:${number}?body=${encoded}`;
}

export function canOpenSms(): boolean {
  if (typeof navigator === "undefined") return false;
  return /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
}
