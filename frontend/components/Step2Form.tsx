"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { generateStep2 } from "@/lib/api";
import type { Step1Data, Step2Data } from "@/lib/api";
import { STEP_DATA_KEY } from "@/lib/storage-keys";
import { toast } from "sonner";
import { Loader2 } from "lucide-react";

const step2Schema = z.object({
  appName: z.string().min(1, "앱 이름을 입력해 주세요."),
  packageId: z.string().min(1, "패키지 ID를 입력해 주세요."),
  privacyPolicyUrl: z.string().url("올바른 URL을 입력해 주세요."),
  supportUrl: z.string().url("올바른 URL을 입력해 주세요."),
  versionName: z.string().min(1, "버전 이름을 입력해 주세요."),
  versionCode: z.number().int().min(1, "1 이상의 숫자를 입력해 주세요."),
});

type Step2FormValues = z.infer<typeof step2Schema>;

export function Step2Form() {
  const router = useRouter();
  const [step1, setStep1] = useState<Step1Data | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const form = useForm<Step2FormValues>({
    resolver: zodResolver(step2Schema),
    defaultValues: {
      appName: "",
      packageId: "",
      privacyPolicyUrl: "",
      supportUrl: "",
      versionName: "1.0.0",
      versionCode: 1,
    },
  });

  useEffect(() => {
    const raw = sessionStorage.getItem(STEP_DATA_KEY);
    if (!raw) {
      toast.error("1단계 데이터가 없습니다. 처음부터 시작해 주세요.");
      router.replace("/");
      return;
    }

    const data = JSON.parse(raw) as { step1: Step1Data };
    setStep1(data.step1);

    generateStep2(data.step1.webUrl)
      .then((generated) => {
        form.reset({
          appName: generated.appName,
          packageId: generated.packageId,
          privacyPolicyUrl: generated.privacyPolicyUrl,
          supportUrl: generated.supportUrl,
          versionName: generated.versionName,
          versionCode: generated.versionCode,
        });
      })
      .catch(() => {
        toast.error("2단계 데이터를 불러오지 못했습니다.");
      })
      .finally(() => {
        setIsLoading(false);
      });
  }, [router, form]);

  async function onSubmit(values: Step2FormValues) {
    if (!step1) return;

    setIsSubmitting(true);
    try {
      const step2Data: Step2Data = {
        appName: values.appName,
        packageId: values.packageId,
        privacyPolicyUrl: values.privacyPolicyUrl,
        supportUrl: values.supportUrl,
        versionName: values.versionName,
        versionCode: values.versionCode,
      };

      const stored = JSON.parse(sessionStorage.getItem(STEP_DATA_KEY) || "{}");
      stored.step2 = step2Data;
      sessionStorage.setItem(STEP_DATA_KEY, JSON.stringify(stored));

      router.push("/step3");
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "저장에 실패했습니다.");
    } finally {
      setIsSubmitting(false);
    }
  }

  if (isLoading || !step1) {
    return (
      <Card className="w-full max-w-md">
        <CardContent className="flex min-h-[200px] items-center justify-center py-12">
          <Loader2 className="size-8 animate-spin text-muted-foreground" />
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className="w-full max-w-md">
      <CardHeader>
        <CardTitle>2단계 — 자동 생성 + 수정</CardTitle>
        <CardDescription>
          도메인 기반으로 자동 생성된 값입니다. 필요 시 수정해 주세요.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-6">
            <FormField
              control={form.control}
              name="appName"
              render={({ field }) => (
                <FormItem>
                  <FormLabel className="flex items-center gap-2">
                    앱 이름
                    <Badge variant="secondary" className="text-xs">자동 생성됨</Badge>
                  </FormLabel>
                  <FormControl>
                    <Input placeholder="Myplatform" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="packageId"
              render={({ field }) => (
                <FormItem>
                  <FormLabel className="flex items-center gap-2">
                    패키지 ID
                    <Badge variant="secondary" className="text-xs">자동 생성됨</Badge>
                  </FormLabel>
                  <FormControl>
                    <Input placeholder="com.myplatform.app" {...field} />
                  </FormControl>
                  <FormDescription>Android 패키지 식별자</FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="privacyPolicyUrl"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>개인정보처리방침 URL</FormLabel>
                  <FormControl>
                    <Input type="url" placeholder="https://example.com/privacy" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="supportUrl"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>지원/문의 URL</FormLabel>
                  <FormControl>
                    <Input type="url" placeholder="https://example.com/contact" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="grid grid-cols-2 gap-4">
              <FormField
                control={form.control}
                name="versionName"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>버전 이름</FormLabel>
                    <FormControl>
                      <Input placeholder="1.0.0" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="versionCode"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>버전 코드</FormLabel>
                    <FormControl>
                      <Input
                      type="number"
                      min={1}
                      {...field}
                      value={field.value}
                      onChange={(e) => {
                        const v = parseInt(e.target.value, 10);
                        field.onChange(isNaN(v) ? 1 : v);
                      }}
                    />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <div className="flex gap-2">
              <Button
                type="button"
                variant="outline"
                className="flex-1"
                onClick={() => router.back()}
              >
                이전
              </Button>
              <Button type="submit" className="flex-1" disabled={isSubmitting}>
                {isSubmitting ? (
                  <>
                    <Loader2 className="mr-2 size-4 animate-spin" />
                    저장 중...
                  </>
                ) : (
                  "다음 (3단계)"
                )}
              </Button>
            </div>
          </form>
        </Form>
      </CardContent>
    </Card>
  );
}
