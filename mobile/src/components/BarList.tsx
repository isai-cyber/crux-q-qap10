import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, font, radius, spacing } from '../theme/tokens';

type Fila = { estado: string; total: number };

/** Barras horizontales de una sola tonalidad — espejo del .barlist web. */
export default function BarList({ filas }: { filas: Fila[] }) {
  const max = Math.max(1, ...filas.map((f) => f.total));
  return (
    <View style={styles.lista}>
      {filas.map((fila) => (
        <View key={fila.estado} style={styles.fila}>
          <View style={styles.cabecera}>
            <Text style={styles.etiqueta}>{fila.estado.replace(/_/g, ' ')}</Text>
            <Text style={styles.valor}>{fila.total}</Text>
          </View>
          <View style={styles.pista}>
            <View
              style={[
                styles.relleno,
                { width: `${Math.max(4, Math.round((fila.total / max) * 100))}%` },
              ]}
            />
          </View>
        </View>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  lista: { gap: spacing.md },
  fila: { gap: 4 },
  cabecera: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'baseline',
  },
  etiqueta: {
    fontSize: 13,
    fontWeight: '600',
    color: colors.textMuted,
    textTransform: 'capitalize',
  },
  valor: {
    fontSize: 13,
    fontWeight: '600',
    color: colors.text,
    fontFamily: font.mono,
  },
  pista: {
    height: 8,
    backgroundColor: colors.surfaceMuted,
    borderRadius: radius.sm,
    overflow: 'hidden',
  },
  relleno: {
    height: '100%',
    backgroundColor: colors.blue,
    borderRadius: radius.sm,
  },
});
