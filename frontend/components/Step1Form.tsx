"use client";

import { useState } from "react";
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
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { uploadFile } from "@/lib/api";
import type { Step1Data } from "@/lib/api";
import { STEP_DATA_KEY } from "@/lib/storage-keys";
import { toast } from "sonner";
import { Loader2 } from "lucide-react";

const step1Schema = z.object({
  webUrl: z.string().url("올바른 URL을 입력해 주세요."),
  appType: z.enum(["webview", "hybrid"]),
});

type Step1FormValues = z.infer<typeof step1Schema>;

export function Step1Form() {
  const router = useRouter();
  const [appIconFile, setAppIconFile] = useState<File | null>(null);
  const [splashFile, setSplashFile] = useState<File | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const form = useForm<Step1FormValues>({
    resolver: zodResolver(step1Schema),
    defaultValues: {
      webUrl: "",
      appType: "webview",
    },
  });

  async function onSubmit(values: Step1FormValues) {
    if (!appIconFile) {
      toast.error("앱 아이콘을 선택해 주세요.");
      return;
    }

    setIsSubmitting(true);
    try {
      const [iconRes, splashRes] = await Promise.all([
        uploadFile(appIconFile),
        splashFile ? uploadFile(splashFile) : Promise.resolve(null),
      ]);

      const step1Data: Step1Data = {
        webUrl: values.webUrl,
        appType: values.appType,
        appIconPath: iconRes.path,
        splashImagePath: splashRes?.path ?? null,
      };

      sessionStorage.setItem(STEP_DATA_KEY, JSON.stringify({
        step1: step1Data,
      }));

      router.push("/step2");
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "업로드에 실패했습니다.");
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <Card className="w-full max-w-md">
      <CardHeader>
        <CardTitle>1단계 — 최소 정보 수집</CardTitle>
        <CardDescription>
          웹 URL과 앱 아이콘을 입력해 주세요. (MVP: 웹뷰만 지원)
        </CardDescription>
      </CardHeader>
      <CardContent>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-6">
            <FormField
              control={form.control}
              name="webUrl"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>웹 URL</FormLabel>
                  <FormControl>
                    <Input
                      placeholder="https://example.com"
                      type="url"
                      {...field}
                    />
                  </FormControl>
                  <FormDescription>로드할 웹사이트 주소 (https 권장)</FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="appType"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>앱 유형</FormLabel>
                  <FormControl>
                    <select
                      className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                      {...field}
                    >
                      <option value="webview">웹뷰</option>
                      <option value="hybrid" disabled>하이브리드 (추후 지원)</option>
                    </select>
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="space-y-2">
              <FormLabel>앱 아이콘 *</FormLabel>
              <FormDescription>512×512 px 이상 권장 (정사각형 PNG/JPEG)</FormDescription>
              <div className="flex items-center gap-4">
                <Avatar className="size-16">
                  {appIconFile ? (
                    <AvatarImage src={URL.createObjectURL(appIconFile)} alt="아이콘" />
                  ) : null}
                  <AvatarFallback>아이콘</AvatarFallback>
                </Avatar>
                <div className="flex-1">
                  <Input
                    type="file"
                    accept="image/png,image/jpeg,image/jpg,image/webp"
                    onChange={(e) => {
                      const f = e.target.files?.[0];
                      if (f) setAppIconFile(f);
                    }}
                  />
                  <p className="mt-1 text-xs text-muted-foreground">
                    PNG/JPG, 512x512 이상 권장
                  </p>
                </div>
              </div>
            </div>

            <div className="space-y-2">
              <FormLabel>스플래시 이미지 (선택)</FormLabel>
              <Input
                type="file"
                accept="image/png,image/jpeg,image/jpg"
                onChange={(e) => {
                  const f = e.target.files?.[0];
                  setSplashFile(f ?? null);
                }}
              />
            </div>

            <Button type="submit" className="w-full" disabled={isSubmitting}>
              {isSubmitting ? (
                <>
                  <Loader2 className="mr-2 size-4 animate-spin" />
                  업로드 중...
                </>
              ) : (
                "다음 (2단계)"
              )}
            </Button>
          </form>
        </Form>
      </CardContent>
    </Card>
  );
}
