import React from 'react';
import { Text, View, StyleSheet } from 'react-native';
import { colors, font, spacing } from '../theme/tokens';

export default function EmptyState({ mensaje }: { mensaje: string }) {
  return (
    <View style={styles.contenedor}>
      <Text style={styles.mensaje}>{mensaje}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  contenedor: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: spacing.xxl,
    gap: spacing.sm,
  },
  mensaje: {
    color: colors.textSoft,
    fontSize: 14,
    textAlign: 'center',
    fontFamily: font.regular,
  },
});
