import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, font, radius, spacing } from '../theme/tokens';

type Props = {
  etiqueta: string;
  valor: number | string;
  color: string;
  icono?: string;
};

/** Tile de KPI con barra de acento izquierda — espejo del dashboard web. */
export default function KpiCard({ etiqueta, valor, color }: Props) {
  return (
    <View style={[styles.card, { borderLeftColor: color }]}>
      <Text style={styles.etiqueta}>{etiqueta}</Text>
      <Text style={[styles.valor, { color }]}>{valor}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    flex: 1,
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    borderLeftWidth: 3,
    padding: spacing.lg,
    gap: 4,
    shadowColor: colors.text,
    shadowOpacity: 0.06,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 2 },
    elevation: 2,
  },
  etiqueta: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.textSoft,
  },
  valor: {
    fontSize: 28,
    fontWeight: '700',
    letterSpacing: -0.5,
    fontFamily: font.bold,
  },
});
