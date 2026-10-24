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
}

/** Large "Scan" button that opens the camera in a sheet; the sheet closes after one code (spec SC-007). */
export function BarcodeScanner({ scanLabel, permissionLabel, closeLabel, onScanned }: BarcodeScannerProps) {
  const { t } = useLedgerTranslations();
  const [permission, requestPermission] = useCameraPermissions();
  const [open, setOpen] = useState(false);
  // onBarcodeScanned fires for every frame while the code is visible: accept only the first one.
  const handled = useRef(false);

  function start(): void {
    handled.current = false;
    setOpen(true);
  }

  function askPermission(): void {
    if (permission && !permission.canAskAgain) void Linking.openSettings();
    else void requestPermission();
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
          <View className="items-center gap-3 rounded-tile bg-viewfinder p-6">
            <Icon name="camera-outline" size={32} className="text-surface" />
            <Text className="type-body text-center text-surface">{permissionLabel}</Text>
            <Pressable accessibilityRole="button" className="ui-button bg-surface" onPress={askPermission}>
              <Text className="type-button text-ink">{t.allowCamera}</Text>
            </Pressable>
          </View>
        )}
        <Pressable accessibilityRole="button" className="ui-button-outline" onPress={() => setOpen(false)}>
          <Text className="type-button text-ink">{closeLabel}</Text>
        </Pressable>
      </BottomSheet>
    </>
  );
}
