import { Ionicons } from '@expo/vector-icons';
import { cssInterop } from 'nativewind';
import { ActivityIndicator, TextInput } from 'react-native';
import { GestureHandlerRootView } from 'react-native-gesture-handler';

cssInterop(Ionicons, {
  className: { target: 'style', nativeStyleToProp: { color: true } },
});

cssInterop(TextInput, {
  className: { target: 'style', nativeStyleToProp: { textAlign: true } },
  placeholderClassName: { target: false, nativeStyleToProp: { color: 'placeholderTextColor' } },
});

cssInterop(ActivityIndicator, {
  className: { target: 'style', nativeStyleToProp: { color: true } },
});

cssInterop(GestureHandlerRootView, { className: 'style' });
