"use client"

import { useState, useEffect } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Calendar, Clock, User, PackageIcon, Plus, Eye } from "lucide-react"

interface Treatment {
  id: number
  package_name: string
  sessions_total: number
  sessions_completed: number
  status: string
  start_date: string
  end_date?: string
}

interface Appointment {
  id: number
  package_name: string
  appointment_date: string
  status: string
  duration_minutes: number
}

interface AvailablePackage {
  id: number
  name: string
  description: string
  price: number
  duration_minutes: number
  sessions_included: number
}

export default function ClientDashboard() {
  const [treatments, setTreatments] = useState<Treatment[]>([])
  const [availablePackages, setAvailablePackages] = useState<AvailablePackage[]>([])
  const [appointments, setAppointments] = useState<Appointment[]>([])
  const [loading, setLoading] = useState(true)

  const colors = {
    primary: "#047475",
    secondary: "#aec2c0",
    accent: "#ebe4c7",
    warm: "#b08660",
  }

  useEffect(() => {
    // Simulated data - replace with actual API calls
    setTimeout(() => {
      setTreatments([
        {
          id: 1,
          package_name: "Suero Vitamina C",
          sessions_total: 3,
          sessions_completed: 1,
          status: "active",
          start_date: "2024-01-15",
        },
        {
          id: 2,
          package_name: "Suero Ácido Hialurónico",
          sessions_total: 1,
          sessions_completed: 1,
          status: "completed",
          start_date: "2024-01-10",
          end_date: "2024-01-10",
        },
      ])

      setAvailablePackages([
        {
          id: 1,
          name: "Suero Vitamina C",
          description: "Tratamiento antioxidante con vitamina C para rejuvenecimiento facial",
          price: 150.0,
          duration_minutes: 45,
          sessions_included: 1,
        },
        {
          id: 2,
          name: "Suero Ácido Hialurónico",
          description: "Hidratación profunda con ácido hialurónico",
          price: 180.0,
          duration_minutes: 60,
          sessions_included: 1,
        },
        {
          id: 3,
          name: "Paquete Rejuvenecimiento",
          description: "Combinación de sueros para anti-aging completo",
          price: 450.0,
          duration_minutes: 90,
          sessions_included: 3,
        },
      ])

      setAppointments([
        {
          id: 1,
          package_name: "Suero Vitamina C",
          appointment_date: "2024-01-25T10:00:00",
          status: "scheduled",
          duration_minutes: 45,
        },
      ])

      setLoading(false)
    }, 1000)
  }, [])

  const getStatusColor = (status: string) => {
    switch (status) {
      case "active":
        return colors.primary
      case "completed":
        return colors.warm
      case "scheduled":
        return colors.secondary
      default:
        return "#6b7280"
    }
  }

  const getStatusText = (status: string) => {
    switch (status) {
      case "active":
        return "Activo"
      case "completed":
        return "Completado"
      case "scheduled":
        return "Programada"
      case "cancelled":
        return "Cancelada"
      default:
        return status
    }
  }

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center" style={{ backgroundColor: colors.accent }}>
        <div className="text-center">
          <div
            className="animate-spin rounded-full h-12 w-12 border-b-2 mx-auto mb-4"
            style={{ borderColor: colors.primary }}
          ></div>
          <p style={{ color: colors.primary }}>Cargando tu información...</p>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen" style={{ backgroundColor: colors.accent }}>
      {/* Header */}
      <header className="shadow-sm" style={{ backgroundColor: colors.primary }}>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-3">
              <div
                className="w-10 h-10 rounded-full flex items-center justify-center"
                style={{ backgroundColor: colors.accent }}
              >
                <User className="w-6 h-6" style={{ color: colors.primary }} />
              </div>
              <div>
                <h1 className="text-xl font-semibold text-white">Portal del Cliente</h1>
                <p className="text-sm" style={{ color: colors.secondary }}>
                  Bienvenido a EMUNA
                </p>
              </div>
            </div>
            <Button variant="outline" className="text-white border-white hover:bg-white/10 bg-transparent">
              Cerrar Sesión
            </Button>
          </div>
        </div>
      </header>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Tratamientos Activos */}
        <section className="mb-8">
          <div className="flex items-center justify-between mb-6">
            <h2 className="text-2xl font-bold" style={{ color: colors.primary }}>
              Mis Tratamientos
            </h2>
            <Badge variant="secondary" style={{ backgroundColor: colors.secondary, color: colors.primary }}>
              {treatments.filter((t) => t.status === "active").length} Activos
            </Badge>
          </div>

          <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            {treatments.map((treatment) => (
              <Card key={treatment.id} className="border-2" style={{ borderColor: colors.secondary }}>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <CardTitle className="text-lg" style={{ color: colors.primary }}>
                      {treatment.package_name}
                    </CardTitle>
                    <Badge style={{ backgroundColor: getStatusColor(treatment.status), color: "white" }}>
                      {getStatusText(treatment.status)}
                    </Badge>
                  </div>
                </CardHeader>
                <CardContent>
                  <div className="space-y-3">
                    <div className="flex items-center justify-between">
                      <span className="text-sm font-medium" style={{ color: colors.warm }}>
                        Progreso
                      </span>
                      <span className="text-sm" style={{ color: colors.primary }}>
                        {treatment.sessions_completed}/{treatment.sessions_total} sesiones
                      </span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div
                        className="h-2 rounded-full transition-all duration-300"
                        style={{
                          backgroundColor: colors.primary,
                          width: `${(treatment.sessions_completed / treatment.sessions_total) * 100}%`,
                        }}
                      ></div>
                    </div>
                    <div className="flex items-center text-sm" style={{ color: colors.warm }}>
                      <Calendar className="w-4 h-4 mr-2" />
                      Inicio: {new Date(treatment.start_date).toLocaleDateString()}
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </section>

        {/* Próximas Citas */}
        <section className="mb-8">
          <h2 className="text-2xl font-bold mb-6" style={{ color: colors.primary }}>
            Próximas Citas
          </h2>

          <div className="grid gap-4">
            {appointments.map((appointment) => (
              <Card key={appointment.id} className="border-l-4" style={{ borderLeftColor: colors.primary }}>
                <CardContent className="p-6">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                      <div
                        className="w-12 h-12 rounded-full flex items-center justify-center"
                        style={{ backgroundColor: colors.accent }}
                      >
                        <PackageIcon className="w-6 h-6" style={{ color: colors.primary }} />
                      </div>
                      <div>
                        <h3 className="font-semibold" style={{ color: colors.primary }}>
                          {appointment.package_name}
                        </h3>
                        <div className="flex items-center text-sm space-x-4 mt-1" style={{ color: colors.warm }}>
                          <span className="flex items-center">
                            <Calendar className="w-4 h-4 mr-1" />
                            {new Date(appointment.appointment_date).toLocaleDateString()}
                          </span>
                          <span className="flex items-center">
                            <Clock className="w-4 h-4 mr-1" />
                            {new Date(appointment.appointment_date).toLocaleTimeString([], {
                              hour: "2-digit",
                              minute: "2-digit",
                            })}
                          </span>
                          <span>{appointment.duration_minutes} min</span>
                        </div>
                      </div>
                    </div>
                    <div className="flex space-x-2">
                      <Button
                        variant="outline"
                        size="sm"
                        style={{ borderColor: colors.secondary, color: colors.primary }}
                      >
                        <Eye className="w-4 h-4 mr-2" />
                        Ver Detalles
                      </Button>
                      <Button size="sm" style={{ backgroundColor: colors.warm, color: "white" }}>
                        Reagendar
                      </Button>
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </section>

        {/* Paquetes Disponibles */}
        <section>
          <div className="flex items-center justify-between mb-6">
            <h2 className="text-2xl font-bold" style={{ color: colors.primary }}>
              Paquetes Disponibles
            </h2>
            <Button style={{ backgroundColor: colors.primary, color: "white" }}>
              <Plus className="w-4 h-4 mr-2" />
              Agendar Nueva Cita
            </Button>
          </div>

          <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            {availablePackages.map((pkg) => (
              <Card
                key={pkg.id}
                className="hover:shadow-lg transition-shadow border-2"
                style={{ borderColor: colors.secondary }}
              >
                <CardHeader>
                  <CardTitle style={{ color: colors.primary }}>{pkg.name}</CardTitle>
                  <CardDescription>{pkg.description}</CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="space-y-4">
                    <div className="flex items-center justify-between">
                      <span className="text-2xl font-bold" style={{ color: colors.warm }}>
                        ${pkg.price.toFixed(2)}
                      </span>
                      <Badge variant="secondary" style={{ backgroundColor: colors.accent, color: colors.primary }}>
                        {pkg.sessions_included} sesión{pkg.sessions_included > 1 ? "es" : ""}
                      </Badge>
                    </div>
                    <div className="flex items-center text-sm" style={{ color: colors.warm }}>
                      <Clock className="w-4 h-4 mr-2" />
                      {pkg.duration_minutes} minutos
                    </div>
                    <Button className="w-full" style={{ backgroundColor: colors.primary, color: "white" }}>
                      Agendar Cita
                    </Button>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </section>
      </div>
    </div>
  )
}
