import { Step1Form } from "@/components/Step1Form";

export default function Home() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center p-6">
      <main className="flex w-full max-w-md flex-col gap-6">
        <Step1Form />
      </main>
    </div>
  );
}
