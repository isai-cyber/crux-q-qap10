import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import {
  colors,
  estadoColor,
  estadoLabel,
  font,
  prioridadColor,
  prioridadLabel,
  radius,
  spacing,
  type EstadoTicket,
  type Prioridad,
} from '../theme/tokens';

/** Chip suave (tinte + texto de color) — mismo estilo soft que la web. */
export function EstadoChip({ estado }: { estado: string }) {
  const color = estadoColor[estado as EstadoTicket] ?? colors.textSoft;
  return (
    <View style={[styles.chip, { backgroundColor: `${color}1A`, borderColor: `${color}44` }]}>
      <Text style={[styles.texto, { color }]}>{estadoLabel[estado as EstadoTicket] ?? estado}</Text>
    </View>
  );
}

export function PrioridadBadge({ prioridad }: { prioridad: string | null }) {
  if (!prioridad) return null;
  const color = prioridadColor[prioridad as Prioridad] ?? colors.textSoft;
  return (
    <View style={[styles.chip, { backgroundColor: `${color}1A`, borderColor: `${color}44` }]}>
      <Text style={[styles.texto, { color }]}>
        {prioridadLabel[prioridad as Prioridad] ?? prioridad}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  chip: {
    alignSelf: 'flex-start',
    borderRadius: radius.sm,
    borderWidth: 1,
    paddingHorizontal: spacing.sm + 2,
    paddingVertical: 3,
  },
  texto: {
    fontSize: 11,
    fontWeight: '700',
    fontFamily: font.semiBold,
    textTransform: 'uppercase',
    letterSpacing: 0.3,
  },
});
