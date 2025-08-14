<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ValidationController extends Controller
{
    /**
     * قواعد التحقق المشتركة
     */
    public static function getCommonRules()
    {
        return [
            'country_rules' => 'required|string|min:2|max:100|regex:/^[\p{L}\s\-]+$/u',
            'city_rules' => 'required|string|min:2|max:100|regex:/^[\p{L}\s\-]+$/u',
            'name_rules' => 'required|string|min:2|max:50|regex:/^[\p{L}\s]+$/u',
            'phone_rules' => 'required|string|regex:/^[+]?[0-9]{10,15}$/|unique:users,phone_number',
            'email_rules' => 'required|string|email:rfc,dns|max:255|unique:users,email',
            'password_rules' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            'weight_rules' => 'required|numeric|min:0.1|max:50',
            'price_rules' => 'required|numeric|min:1|max:10000',
            'date_rules' => 'required|date|after_or_equal:today',
        ];
    }

    /**
     * رسائل الأخطاء المخصصة
     */
    public static function getCustomMessages()
    {
        return [
            'required' => 'حقل :attribute مطلوب',
            'string' => 'حقل :attribute يجب أن يكون نص',
            'min' => 'حقل :attribute يجب أن يكون على الأقل :min أحرف',
            'max' => 'حقل :attribute يجب ألا يزيد عن :max أحرف',
            'email' => 'حقل :attribute يجب أن يكون بريد إلكتروني صحيح',
            'unique' => 'هذا :attribute مستخدم بالفعل',
            'confirmed' => 'تأكيد :attribute غير متطابق',
            'regex' => 'تنسيق :attribute غير صحيح',
            'numeric' => 'حقل :attribute يجب أن يكون رقم',
            'date' => 'حقل :attribute يجب أن يكون تاريخ صحيح',
            'after_or_equal' => 'حقل :attribute يجب أن يكون بعد أو يساوي :date',
            'before' => 'حقل :attribute يجب أن يكون قبل :date',
            'in' => 'القيمة المحددة لحقل :attribute غير صحيحة',
        ];
    }

    /**
     * أسماء الحقول بالعربية
     */
    public static function getAttributeNames()
    {
        return [
            'first_name' => 'الاسم الأول',
            'last_name' => 'الاسم الأخير',
            'email' => 'البريد الإلكتروني',
            'password' => 'كلمة المرور',
            'phone_number' => 'رقم الهاتف',
            'date_of_birth' => 'تاريخ الميلاد',
            'gender' => 'الجنس',
            'type' => 'نوع الحساب',
            'from_country' => 'البلد المرسل منه',
            'from_city' => 'المدينة المرسل منها',
            'to_country' => 'البلد المرسل إليه',
            'to_city' => 'المدينة المرسل إليها',
            'weight' => 'الوزن',
            'category' => 'الفئة',
            'description' => 'الوصف',
            'offered_price' => 'السعر المعروض',
            'delivery_from_date' => 'تاريخ بداية التسليم',
            'delivery_to_date' => 'تاريخ نهاية التسليم',
            'travel_date' => 'تاريخ السفر',
            'max_weight' => 'الحد الأقصى للوزن',
        ];
    }

    /**
     * إنشاء validator مخصص
     */
    public static function make(array $data, array $rules, array $messages = [], array $attributes = [])
    {
        $customMessages = array_merge(self::getCustomMessages(), $messages);
        $customAttributes = array_merge(self::getAttributeNames(), $attributes);

        return Validator::make($data, $rules, $customMessages, $customAttributes);
    }
}