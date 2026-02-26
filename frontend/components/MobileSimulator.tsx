"use client";

interface MobileSimulatorProps {
  webUrl: string;
  splashUrl?: string | null;
  appName: string;
}

export function MobileSimulator({ webUrl, splashUrl, appName }: MobileSimulatorProps) {
  return (
    <div className="mx-auto w-full max-w-[280px]">
      {/* 모바일 프레임 (iPhone 스타일) */}
      <div className="overflow-hidden rounded-[2.5rem] border-[8px] border-neutral-800 bg-neutral-900 shadow-xl">
        {/* 노치 */}
        <div className="flex justify-center bg-neutral-900 py-2">
          <div className="h-5 w-24 rounded-full bg-neutral-800" />
        </div>

        {/* 화면 영역 */}
        <div className="aspect-[9/19] bg-white dark:bg-neutral-950">
          {/* 스플래시 또는 웹뷰 */}
          {splashUrl ? (
            <div className="flex h-full flex-col">
              <div className="flex flex-1 items-center justify-center bg-muted p-4">
                <img
                  src={splashUrl}
                  alt="스플래시"
                  className="max-h-full max-w-full object-contain"
                />
              </div>
              <div className="border-t bg-muted/50 px-4 py-2 text-center text-xs text-muted-foreground">
                → 웹 로드 중...
              </div>
            </div>
          ) : (
            <div className="flex h-full flex-col">
              <div className="flex flex-1 flex-col items-center justify-center gap-2 bg-muted/30 p-4">
                <div className="text-sm font-medium">{appName}</div>
                <iframe
                  src={webUrl}
                  title="웹 미리보기"
                  className="h-[200px] w-full rounded border-0"
                  sandbox="allow-scripts"
                />
                <p className="text-xs text-muted-foreground">
                  CORS 제한으로 일부 사이트는 미리보기가 제한될 수 있습니다.
                </p>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
