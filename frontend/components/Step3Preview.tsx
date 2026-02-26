"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { MobileSimulator } from "@/components/MobileSimulator";
import { createBuild } from "@/lib/api";
import type { Step1Data, Step2Data } from "@/lib/api";
import { STEP_DATA_KEY } from "@/lib/storage-keys";
import { toast } from "sonner";
import { AlertCircle, Loader2 } from "lucide-react";

export function Step3Preview() {
  const router = useRouter();
  const [step1, setStep1] = useState<Step1Data | null>(null);
  const [step2, setStep2] = useState<Step2Data | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isBuilding, setIsBuilding] = useState(false);
  const [buildError, setBuildError] = useState<string | null>(null);
  const [iconLoadError, setIconLoadError] = useState(false);

  useEffect(() => {
    const raw = sessionStorage.getItem(STEP_DATA_KEY);
    if (!raw) {
      toast.error("이전 단계 데이터가 없습니다. 처음부터 시작해 주세요.");
      router.replace("/");
      return;
    }

    const data = JSON.parse(raw) as { step1?: Step1Data; step2?: Step2Data };
    if (!data.step1 || !data.step2) {
      toast.error("2단계를 먼저 완료해 주세요.");
      router.replace("/step2");
      return;
    }

    setStep1(data.step1);
    setStep2(data.step2);
    setIsLoading(false);
  }, [router]);

  async function handleBuildStart() {
    if (!step1 || !step2) return;

    setIsBuilding(true);
    setBuildError(null);
    try {
      const res = await createBuild(step1, step2);
      sessionStorage.removeItem(STEP_DATA_KEY);
      router.push(`/build/${res.buildId}`);
      toast.success("빌드가 완료되었습니다.");
    } catch (e) {
      const msg = e instanceof Error ? e.message : "빌드 요청에 실패했습니다.";
      setBuildError(msg);
      toast.error(msg);
    } finally {
      setIsBuilding(false);
    }
  }

  if (isLoading || !step1 || !step2) {
    return (
      <Card className="w-full max-w-md">
        <CardContent className="flex min-h-[200px] items-center justify-center py-12">
          <Loader2 className="size-8 animate-spin text-muted-foreground" />
        </CardContent>
      </Card>
    );
  }

  const splashUrl = step1.splashImagePath
    ? `/storage/${step1.splashImagePath}`
    : null;

  return (
    <div className="flex w-full max-w-md flex-col gap-6">
      <Card>
        <CardHeader>
          <CardTitle>3단계 — 시뮬레이션 + 최종 확인</CardTitle>
          <CardDescription>
            앱 미리보기와 요약 정보를 확인한 뒤 빌드를 시작해 주세요.
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-6">
          <MobileSimulator
            webUrl={step1.webUrl}
            splashUrl={splashUrl}
            appName={step2.appName}
          />

          <Separator />

          <div className="space-y-2">
            <h4 className="text-sm font-medium">APK에 적용될 앱 아이콘</h4>
            <p className="text-xs text-muted-foreground">
              아래 아이콘이 APK 빌드 시 사용됩니다. 확인 후 빌드를 시작하세요.
            </p>
            <div className="flex items-center gap-4 rounded-lg border bg-muted/30 p-4">
              {!iconLoadError ? (
                <img
                  src={`/api/upload/preview?path=${encodeURIComponent(step1.appIconPath)}`}
                  alt="앱 아이콘"
                  className="size-16 rounded-xl object-contain"
                  onError={() => setIconLoadError(true)}
                />
              ) : (
                <div className="flex size-16 items-center justify-center rounded-xl bg-muted text-xs text-muted-foreground">
                  미리보기 없음
                </div>
              )}
              <div className="flex-1 text-sm">
                <p className="font-medium">등록된 아이콘</p>
                <p className="text-muted-foreground">
                  이 이미지가 앱 런처에 표시됩니다.
                </p>
              </div>
            </div>
          </div>

          <Separator />

          <div className="space-y-2">
            <h4 className="text-sm font-medium">요약 정보</h4>
            <dl className="grid gap-2 text-sm">
              <div className="flex justify-between">
                <dt className="text-muted-foreground">앱 이름</dt>
                <dd>{step2.appName}</dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-muted-foreground">패키지 ID</dt>
                <dd className="break-all font-mono text-xs">{step2.packageId}</dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-muted-foreground">웹 URL</dt>
                <dd className="break-all text-xs">{step1.webUrl}</dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-muted-foreground">버전</dt>
                <dd>{step2.versionName} ({step2.versionCode})</dd>
              </div>
            </dl>
          </div>

          {buildError && (
            <Alert variant="destructive">
              <AlertCircle className="size-4" />
              <AlertDescription>
                <span className="font-medium">빌드 실패</span>
                <pre className="mt-2 max-h-40 overflow-auto whitespace-pre-wrap break-words rounded bg-destructive/10 p-2 text-xs">
                  {buildError}
                </pre>
                <p className="mt-2 text-sm">
                  위 메시지를 확인한 뒤 환경 설정을 점검하거나 다시 시도해 주세요.
                </p>
              </AlertDescription>
            </Alert>
          )}

          <Button
            className="w-full"
            size="lg"
            onClick={handleBuildStart}
            disabled={isBuilding}
          >
            {isBuilding ? (
              <>
                <Loader2 className="mr-2 size-4 animate-spin" />
                빌드 중... (2~5분 소요, 잠시만 기다려 주세요)
              </>
            ) : (
              "빌드 시작"
            )}
          </Button>

          <Button
            variant="outline"
            className="w-full"
            onClick={() => router.back()}
            disabled={isBuilding}
          >
            이전
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
