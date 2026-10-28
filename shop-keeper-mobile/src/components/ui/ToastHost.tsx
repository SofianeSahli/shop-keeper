import { useCallback, useEffect, useState } from 'react';
import { AppState, View } from 'react-native';
import { Directions, GestureDetector, useFlingGesture } from 'react-native-gesture-handler';
import { useDispatch, useSelector } from 'react-redux';
import { RootState } from '@/store';
import type { Toast as ToastModel } from '@/features/feedback/store/feedbackSlice';
import { runToastAction } from '@/features/feedback/toastActions';
import { toastDurationMs } from '@/features/feedback/toastDuration';
import { dismissToast } from '@/features/feedback/toasts';
import { Toast } from './Toast';
import { useReduceMotion } from './useReduceMotion';

const ENTRY_ANIMATION_CLASS = 'animate-toast-in';

function useAppIsActive(): boolean {
  const [isActive, setIsActive] = useState(AppState.currentState === 'active');
  useEffect(() => {
    const subscription = AppState.addEventListener('change', (state) => setIsActive(state === 'active'));
    return () => subscription.remove();
  }, []);
  return isActive;
}

export function ToastHost() {
  const visible = useSelector((state: RootState) => state.feedback.toasts[0]);
  const reduceMotion = useReduceMotion();
  if (!visible) return null;
  return <VisibleToast key={`${visible.id}-${visible.kind}`} toast={visible} reduceMotion={reduceMotion} />;
}

function VisibleToast({ toast, reduceMotion }: { toast: ToastModel; reduceMotion: boolean }) {
  const dispatch = useDispatch();
  const appIsActive = useAppIsActive();
  const [entryClass] = useState(reduceMotion ? '' : ENTRY_ANIMATION_CLASS);

  const dismiss = useCallback(() => dismissToast(dispatch, toast), [dispatch, toast]);

  useEffect(() => {
    const duration = toastDurationMs(toast.kind);
    if (duration === null || !appIsActive) return;
    const timer = setTimeout(dismiss, duration);
    return () => clearTimeout(timer);
  }, [appIsActive, dismiss, toast.kind]);

  const swipeRight = useFlingGesture({ direction: Directions.RIGHT, runOnJS: true, onActivate: dismiss });

  function pressAction(): void {
    if (toast.actionId) runToastAction(toast.actionId);
    dismiss();
  }

  return (
    <View pointerEvents="box-none" className="absolute inset-x-4 top-3 z-50">
      <GestureDetector gesture={swipeRight}>
        <View className={entryClass}>
          <Toast toast={toast} onAction={pressAction} onDismiss={dismiss} />
        </View>
      </GestureDetector>
    </View>
  );
}
