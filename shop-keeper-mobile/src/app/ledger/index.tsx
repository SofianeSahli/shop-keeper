import { Redirect } from 'expo-router';

/** Home lives at `/`; keep `/ledger` working for old links. */
export default function LedgerIndex() {
  return <Redirect href="/" />;
}
