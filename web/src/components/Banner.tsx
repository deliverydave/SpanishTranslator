type Props = {
  text: string;
  tone?: "error" | "info";
};

export function Banner({ text, tone = "error" }: Props) {
  return <div className={`banner ${tone}`}>{text}</div>;
}
