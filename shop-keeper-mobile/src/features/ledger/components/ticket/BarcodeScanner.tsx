import { useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { cssInterop } from 'nativewind';

cssInterop(CameraView, { className: 'style' });

interface BarcodeScannerProps {
  scanLabel: string;
  permissionLabel: string;
  closeLabel: string;
  onScanned: (value: string) => void;
}

export function BarcodeScanner({ scanLabel, permissionLabel, closeLabel, onScanned }: BarcodeScannerProps) {
  const [permission, requestPermission] = useCameraPermissions();
  const [scanning, setScanning] = useState(false);

  if (!scanning) {
    return (
      <Pressable
        accessibilityRole="button"
        className="ledger-touch-target rounded-xl border border-blue-300 px-4 py-3"
        onPress={() => setScanning(true)}
      >
        <Text className="font-semibold text-blue-800">{scanLabel}</Text>
      </Pressable>
    );
  }

  if (!permission?.granted) {
    return (
      <View className="gap-3 rounded-xl bg-white p-4">
        <Text className="text-slate-700">{permissionLabel}</Text>
        <Pressable accessibilityRole="button" className="ledger-primary-button" onPress={() => void requestPermission()}>
          <Text className="font-semibold text-white">{scanLabel}</Text>
        </Pressable>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={closeLabel}
          className="ledger-touch-target"
          onPress={() => setScanning(false)}
        >
          <Text className="text-center text-slate-700">×</Text>
        </Pressable>
      </View>
    );
  }

  return (
    <View className="h-64 overflow-hidden rounded-xl">
      <CameraView
        className="flex-1"
        facing="back"
        barcodeScannerSettings={{ barcodeTypes: ['ean13', 'ean8', 'upc_a', 'upc_e', 'code128', 'code39', 'qr'] }}
        onBarcodeScanned={({ data }) => {
          setScanning(false);
          onScanned(data);
        }}
      />
    </View>
  );
}
