import { Step2Form } from "@/components/Step2Form";

export default function Step2Page() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center p-6">
      <main className="flex w-full max-w-md flex-col gap-6">
        <Step2Form />
      </main>
    </div>
  );
}