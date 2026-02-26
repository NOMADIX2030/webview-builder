const API_BASE = process.env.NEXT_PUBLIC_API_URL || "/api";

export type Step1Data = {
  webUrl: string;
  appType: "webview" | "hybrid";
  appIconPath: string;
  splashImagePath?: string | null;
};

export type Step2Data = {
  appName: string;
  packageId: string;
  privacyPolicyUrl: string;
  supportUrl: string;
  versionName: string;
  versionCode: number;
};

export type Step2Generated = Step2Data;

export type BuildStatus = {
  buildId: string;
  status: "queued" | "building" | "completed" | "failed";
  progress: number;
  message: string;
  artifacts: Record<string, string>;
  createdAt: string | null;
  completedAt: string | null;
};

async function fetchApi<T>(
  path: string,
  options?: RequestInit
): Promise<T> {
  const res = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers: {
      "Content-Type": "application/json",
      ...options?.headers,
    },
  });

  const data = await res.json().catch(() => ({}));

  if (!res.ok) {
    throw new Error("error" in data ? data.error : "요청에 실패했습니다.");
  }

  return data as T;
}

export async function uploadFile(file: File): Promise<{ path: string; url: string }> {
  const formData = new FormData();
  formData.append("file", file);

  const res = await fetch(`${API_BASE}/upload`, {
    method: "POST",
    body: formData,
  });

  const data = await res.json().catch(() => ({}));

  if (!res.ok) {
    throw new Error("error" in data ? data.error : "업로드에 실패했습니다.");
  }

  return data as { path: string; url: string };
}

export async function generateStep2(webUrl: string): Promise<Step2Generated> {
  return fetchApi<Step2Generated>("/build/generate-step2", {
    method: "POST",
    body: JSON.stringify({ webUrl }),
  });
}

export async function createBuild(step1: Step1Data, step2: Step2Data): Promise<{
  buildId: string;
  status: string;
  message: string;
}> {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), 600000);
  try {
    const res = await fetch(`${API_BASE}/build`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
      step1: {
        webUrl: step1.webUrl,
        appType: step1.appType,
        appIconPath: step1.appIconPath,
        splashImagePath: step1.splashImagePath ?? null,
      },
      step2: {
        appName: step2.appName,
        packageId: step2.packageId,
        privacyPolicyUrl: step2.privacyPolicyUrl,
        supportUrl: step2.supportUrl,
        versionName: step2.versionName,
        versionCode: step2.versionCode,
      },
    }),
      signal: controller.signal,
    });
    clearTimeout(timeoutId);
    const data = await res.json();
    if (!res.ok) throw new Error(data.error ?? "빌드 요청에 실패했습니다.");
    return data;
  } catch (e) {
    clearTimeout(timeoutId);
    throw e;
  }
}

export async function getBuildStatus(buildId: string): Promise<BuildStatus> {
  return fetchApi<BuildStatus>(`/build/${buildId}`);
}
