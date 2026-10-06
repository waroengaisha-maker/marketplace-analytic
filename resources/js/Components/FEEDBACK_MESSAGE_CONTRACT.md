# FeedbackMessage Contract

`FeedbackMessage` is the shared presentation component for inline user feedback such as success, informational, warning, and error messages.

## Location

`resources/js/Components/FeedbackMessage.vue`

## Contract

- Wrap PrimeVue `Message`; do not duplicate Message presentation markup in pages when the usage matches this contract.
- `severity` controls the semantic visual tone.
- `variant` supports PrimeVue's `outlined` and `simple` variants.
- `message` provides simple text content; the default slot supports contextual/custom content.
- `closable` and `icon` are optional presentation controls.
- The component contains no business/domain logic.
- Use semantic PrimeVue severity values: `success`, `info`, `warn`, `error`, `secondary`, `contrast`.
- Do not hard-code light/dark colors or page-specific styling inside the component.

## Usage

```vue
<FeedbackMessage message="Import berhasil." severity="success" />
<FeedbackMessage severity="error">Gagal memproses data.</FeedbackMessage>
<FeedbackMessage severity="info" variant="simple" icon="pi pi-info-circle">
    Informasi tambahan.
</FeedbackMessage>
```

## Accessibility

Keep meaningful feedback text available to the user. Do not rely on severity color or iconography alone to communicate the message.

## Validation

```bash
./vendor/bin/sail npm run typecheck
./vendor/bin/sail npm run build
git diff --check
```
