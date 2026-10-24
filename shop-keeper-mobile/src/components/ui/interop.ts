import { Ionicons } from '@expo/vector-icons';
import { cssInterop } from 'nativewind';
import { TextInput } from 'react-native';

// Lets token classes drive color props that are not styles (constitution: no hex values in .tsx).
cssInterop(Ionicons, {
  className: { target: 'style', nativeStyleToProp: { color: true } },
});

cssInterop(TextInput, {
  className: { target: 'style', nativeStyleToProp: { textAlign: true } },
  placeholderClassName: { target: false, nativeStyleToProp: { color: 'placeholderTextColor' } },
});
