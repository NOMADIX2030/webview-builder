import { Step3Preview } from "@/components/Step3Preview";

export default function Step3Page() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center p-6">
      <main className="flex w-full max-w-md flex-col gap-6">
        <Step3Preview />
      </main>
    </div>
  );
}