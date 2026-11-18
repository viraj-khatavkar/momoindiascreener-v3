---
paths:
  - 'resources/js/Components/Form/**'
---

# Form

## Give each form control its own accessible identity
Generate fallback input IDs per component instance with Vue useId; do not generate them in hoisted defineProps defaults. Labels and error messages must reference the actual control. Pass disabled to the native input or select, not only its wrapper.

## Keep input and select dimensions consistent
TextInput and SelectInput use the same 40 px desktop height, 44 px mobile height, label spacing and disabled styling. Use a native select with appearance-none and a decorative chevron so browser defaults cannot change alignment. For long ranking labels, show the full selected text below only when it is clipped; keep labels, hints and error messages tied to unique control IDs.
