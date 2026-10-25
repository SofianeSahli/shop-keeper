import { useRef, useState } from 'react';
import { Linking, Pressable, Text, View } from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { cssInterop } from 'nativewind';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { BottomSheet, Icon } from '@/components/ui';

cssInterop(CameraView, { className: 'style' });

const BARCODE_TYPES = ['ean13', 'ean8', 'upc_a', 'upc_e', 'code128', 'code39', 'qr'] as const;

interface BarcodeScannerProps {
  scanLabel: string;
  permissionLabel: string;
  closeLabel: string;
  onScanned: (value: string) => void;
  /**
   * `sheet` (ticket form): a large Scan button opening the camera in a sheet that closes after one
   * code (spec SC-007, FR-025). `inline` (product form): a viewfinder always on screen (spec US6).
   */
  mode?: 'sheet' | 'inline';
  /** Inline mode: reads codes only while armed; the parent re-arms it with its re-scan button. */
  armed?: boolean;
}

function useAskPermission() {
  const [permission, requestPermission] = useCameraPermissions();
  const ask = () => {
    if (permission && !permission.canAskAgain) void Linking.openSettings();
    else void requestPermission();
  };
  return { permission, ask };
}

function PermissionBlock({ message, onAllow }: { message: string; onAllow: () => void }) {
  const { t } = useLedgerTranslations();
  return (
    <View className="ui-viewfinder gap-3 p-6">
      <Icon name="camera-outline" size={32} className="text-surface" />
      <Text className="type-body text-center text-surface">{message}</Text>
      <Pressable accessibilityRole="button" className="ui-button bg-surface" onPress={onAllow}>
        <Text className="type-button text-ink">{t.allowCamera}</Text>
      </Pressable>
    </View>
  );
}

function InlineScanner({ permissionLabel, onScanned, armed = true }: Pick<BarcodeScannerProps, 'permissionLabel' | 'onScanned' | 'armed'>) {
  const { t } = useLedgerTranslations();
  const { permission, ask } = useAskPermission();
  const [unavailable, setUnavailable] = useState(false);

  if (!permission) return <View className="ui-viewfinder" />;
  if (!permission.granted) return <PermissionBlock message={permissionLabel} onAllow={ask} />;
  if (unavailable) {
    return (
      <View className="ui-viewfinder p-6">
        <Text className="type-body text-center text-surface">{t.noCameraHint}</Text>
      </View>
    );
  }

  return (
    <View accessible accessibilityLabel={t.scanningHint} className="ui-viewfinder">
      <CameraView
        className="absolute inset-0"
        facing="back"
        barcodeScannerSettings={{ barcodeTypes: [...BARCODE_TYPES] }}
        onMountError={() => setUnavailable(true)}
        onBarcodeScanned={armed ? ({ data }) => onScanned(data) : undefined}
      />
      <View className="ui-viewfinder-corner start-6 top-6 rounded-ss-lg border-s-4 border-t-4" />
      <View className="ui-viewfinder-corner end-6 top-6 rounded-se-lg border-e-4 border-t-4" />
      <View className="ui-viewfinder-corner bottom-6 start-6 rounded-es-lg border-b-4 border-s-4" />
      <View className="ui-viewfinder-corner bottom-6 end-6 rounded-ee-lg border-b-4 border-e-4" />
      {armed ? <View className="ui-viewfinder-line" /> : null}
      <Text className="absolute bottom-2 font-plex-semibold text-caption text-surface">{t.scanningHint}</Text>
    </View>
  );
}

export function BarcodeScanner({ scanLabel, permissionLabel, closeLabel, onScanned, mode = 'sheet', armed }: BarcodeScannerProps) {
  const { t } = useLedgerTranslations();
  const { permission, ask } = useAskPermission();
  const [open, setOpen] = useState(false);
  // onBarcodeScanned fires for every frame while the code is visible: accept only the first one.
  const handled = useRef(false);

  if (mode === 'inline') {
    return <InlineScanner permissionLabel={permissionLabel} onScanned={onScanned} armed={armed} />;
  }

  function start(): void {
    handled.current = false;
    setOpen(true);
  }

  return (
    <>
      <Pressable
        accessibilityRole="button"
        className="min-h-[64px] flex-1 flex-row items-center justify-center gap-2 rounded-control-lg bg-primary px-3 active:opacity-90"
        onPress={start}
      >
        <Icon name="scan-outline" size={24} className="text-surface" />
        <Text className="type-button text-surface">{scanLabel}</Text>
      </Pressable>

      <BottomSheet visible={open} title={scanLabel} helper={t.scanningHint} onClose={() => setOpen(false)}>
        {!permission ? null : permission.granted ? (
          <View className="h-64 overflow-hidden rounded-tile bg-viewfinder">
            <CameraView
              className="flex-1"
              facing="back"
              barcodeScannerSettings={{ barcodeTypes: [...BARCODE_TYPES] }}
              onBarcodeScanned={({ data }) => {
                if (handled.current) return;
                handled.current = true;
                setOpen(false);
                onScanned(data);
              }}
            />
          </View>
        ) : (
          <PermissionBlock message={permissionLabel} onAllow={ask} />
        )}
        <Pressable accessibilityRole="button" className="ui-button-outline" onPress={() => setOpen(false)}>
          <Text className="type-button text-ink">{closeLabel}</Text>
        </Pressable>
      </BottomSheet>
    </>
  );
}
