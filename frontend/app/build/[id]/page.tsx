"use client";

import { useEffect, useRef, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { getBuildStatus, type BuildStatus } from "@/lib/api";
import { toast } from "sonner";
import { Loader2, Download, AlertCircle } from "lucide-react";

const POLL_INTERVAL_MS = 3000;

export default function BuildStatusPage() {
  const params = useParams();
  const router = useRouter();
  const buildId = params.id as string;
  const [status, setStatus] = useState<BuildStatus | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [pollCount, setPollCount] = useState(0);
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    if (!buildId) return;

    async function fetchStatus() {
      try {
        const data = await getBuildStatus(buildId);
        setStatus(data);
        setError(null);
        setPollCount((c) => c + 1);

        if (data.status === "completed" || data.status === "failed") {
          return true;
        }
        return false;
      } catch (e) {
        setError(e instanceof Error ? e.message : "상태를 불러오지 못했습니다.");
        toast.error("빌드 상태 조회 실패");
        return true;
      }
    }

    fetchStatus().then((done) => {
      if (done) return;

      intervalRef.current = setInterval(() => {
        fetchStatus().then((d) => {
          if (d && intervalRef.current) {
            clearInterval(intervalRef.current);
            intervalRef.current = null;
          }
        });
      }, POLL_INTERVAL_MS);
    });

    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
        intervalRef.current = null;
      }
    };
  }, [buildId]);

  if (error && !status) {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center p-6">
        <Card className="w-full max-w-md">
          <CardHeader>
            <CardTitle>오류</CardTitle>
            <CardDescription>{error}</CardDescription>
          </CardHeader>
          <CardContent>
            <Button onClick={() => router.push("/")}>홈으로</Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  if (!status) {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center p-6">
        <Loader2 className="size-12 animate-spin text-muted-foreground" />
      </div>
    );
  }

  const isComplete = status.status === "completed";
  const isFailed = status.status === "failed";
  const isInProgress = status.status === "queued" || status.status === "building";

  const elapsed =
    status.createdAt &&
    Math.floor((Date.now() - new Date(status.createdAt).getTime()) / 1000);

  return (
    <div className="flex min-h-screen flex-col items-center justify-center p-6">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            빌드 상태
            {isInProgress && (
              <Loader2 className="size-5 animate-spin text-muted-foreground" />
            )}
          </CardTitle>
          <CardDescription>
            {status.status === "queued" &&
              "이전 방식의 대기 중 빌드입니다. 아래 '새 빌드 시작'으로 처음부터 다시 진행하세요."}
            {status.status === "building" &&
              "Android APK 빌드 중입니다. (보통 2~5분 소요)"}
            {status.status === "completed" && "빌드가 완료되었습니다."}
            {status.status === "failed" && "빌드에 실패했습니다."}
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-6">
          {isInProgress && (
            <p className="flex items-center gap-2 text-sm text-muted-foreground">
              <span className="inline-block size-2 animate-pulse rounded-full bg-primary" />
              {elapsed !== undefined && elapsed > 0
                ? `경과 ${Math.floor(elapsed / 60)}분 ${elapsed % 60}초`
                : "시작 중..."}
              <span className="text-muted-foreground/70">
                · {pollCount}회 확인
              </span>
            </p>
          )}

          <Progress value={status.progress} className="h-2" />

          {status.message && (
            <p className="text-sm text-muted-foreground">{status.message}</p>
          )}

          {isFailed && (
            <Alert variant="destructive">
              <AlertCircle className="size-4" />
              <AlertDescription>
                {status.message}
                <span className="mt-2 block text-sm">
                  다시 시도하려면 아래 "새 빌드 시작"을 클릭해 1단계부터 진행하세요.
                </span>
              </AlertDescription>
            </Alert>
          )}

          {isComplete && Object.keys(status.artifacts).length > 0 && (
            <div className="space-y-2">
              <h4 className="text-sm font-medium">다운로드</h4>
              <div className="flex flex-col gap-2">
                {status.artifacts.apk && (
                  <Button asChild variant="outline" className="w-full">
                    <a href={status.artifacts.apk} download>
                      <Download className="mr-2 size-4" />
                      APK 다운로드
                    </a>
                  </Button>
                )}
                {status.artifacts.ipa && (
                  <Button asChild variant="outline" className="w-full">
                    <a href={status.artifacts.ipa} download>
                      <Download className="mr-2 size-4" />
                      IPA 다운로드
                    </a>
                  </Button>
                )}
                {status.artifacts.keystore && (
                  <Button asChild variant="outline" className="w-full">
                    <a href={status.artifacts.keystore} download>
                      <Download className="mr-2 size-4" />
                      Keystore 다운로드
                    </a>
                  </Button>
                )}
              </div>
            </div>
          )}

          <Button onClick={() => router.push("/")} variant="outline" className="w-full">
            새 빌드 시작
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
