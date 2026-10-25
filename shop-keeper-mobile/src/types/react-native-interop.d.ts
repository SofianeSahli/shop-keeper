import 'react-native';

declare module 'react-native' {
  interface TextInputProps {
    /** Mapped to `placeholderTextColor` by src/components/ui/interop.ts. */
    placeholderClassName?: string;
  }
}
