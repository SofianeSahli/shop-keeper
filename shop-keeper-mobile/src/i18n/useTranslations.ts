import { useSelector } from 'react-redux';
import { RootState } from '@/store';
import { getTranslations } from './index';

export function useLedgerTranslations() {
  const language = useSelector((state: RootState) => state.ledger.selectedLanguage);
  return { language, t: getTranslations(language) };
}
