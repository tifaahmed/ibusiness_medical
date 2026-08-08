<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'question' => [
                    'ar' => 'ليه اختار كارت ASH Health Care عن اي شركة تأمين تانية؟',
                    'en' => 'Why choose the ASH Health Care card over any other insurance company?',
                ],
                'answer' => [
                    'ar' => 'لأن الكارت بيوفر نسب خصومات بتصل الي 80% افضل من اي نظام تأمين وبدون موافقات او جوابات تحويل ومتاح استخدامة كل يوم علي مدار السنة وبدون حد للاستخدام و بأشتراك سنويا وليس شهريا',
                    'en' => 'Because the card offers discounts of up to 80%, better than any insurance plan, with no approvals or referral letters required. It is available for use every day all year round, with no usage limit, and on an annual subscription rather than a monthly one.',
                ],
            ],
            [
                'question' => [
                    'ar' => 'هل في عدد معين اقدر اضيفه في الكارت وكم تكلفة اضافة فرد من افراد الاسرة معايه في الكارت؟',
                    'en' => 'Is there a limit to the number of family members I can add to the card, and what is the cost?',
                ],
                'answer' => [
                    'ar' => 'الكارت شامل افراد الاسرة الدرجة الاولي يعني متاح اضافة الاب والام والزوجه والابناء والاخوات. ومتاح اضافة حتي 15 فرد في العضوية بدون اي رسوم اضافية وكمان الاستفادة بنفس نسب الخصومات الي بيستفيد بيها العضو الاساسي للكارت',
                    'en' => 'The card covers first-degree family members, including father, mother, spouse, children, and siblings. You can add up to 15 members to the membership at no additional cost, and they all benefit from the same discount rates as the primary cardholder.',
                ],
            ],
            [
                'question' => [
                    'ar' => 'هل الكارت بيقدم خدمات مجانية او عروض؟',
                    'en' => 'Does the card offer free services or special deals?',
                ],
                'answer' => [
                    'ar' => 'الكارت بيوفر خدمات مجانية باستمرار زي الكشف المجاني علي الاسنان او العظام والعروض المستمرة في أفضل واشهر مقدمي الخدمة الطبية من حيث الجودة والدقة والاسعار',
                    'en' => 'The card continuously offers free services such as free dental or orthopedic check-ups, along with ongoing deals at the best and most reputable medical service providers in terms of quality, accuracy, and pricing.',
                ],
            ],
            [
                'question' => [
                    'ar' => 'هل الكارت بيدعم جميع التخصصات الطبية و بيغطي محافظات ايه؟',
                    'en' => 'Does the card support all medical specialties, and which governorates does it cover?',
                ],
                'answer' => [
                    'ar' => "الكارت بيوفر خصومات في اكتر من 2500 مقدم خدمة طبية في جميع المحافظات زي القاهرة والجيزة والقليوبية والإسكندرية والمنوفية وأسيوط وقنا وسوهاج والمنصورة\n\n• خصومات في افضل واشهر معامل التحاليل\n• خصومات في افضل واشهر مراكز الاشعة\n• خصومات في أفضل واشهر المستشفيات\n• خصومات في افضل واشهر العيادات\n• خصومات في أفضل واشهر المراكز الطبية\n• خصومات في افضل واشهر مراكز النظارات\n• خصومات في افضل واشهر الصيدليات",
                    'en' => "The card offers discounts at more than 2,500 medical service providers across all governorates including Cairo, Giza, Qalyubia, Alexandria, Menoufia, Asyut, Qena, Sohag, and Mansoura.\n\n• Discounts at the best and most reputable analysis labs\n• Discounts at the best and most reputable radiology centers\n• Discounts at the best and most reputable hospitals\n• Discounts at the best and most reputable clinics\n• Discounts at the best and most reputable medical centers\n• Discounts at the best and most reputable optical centers\n• Discounts at the best and most reputable pharmacies",
                ],
            ],
            [
                'question' => [
                    'ar' => 'هل متاح استخدم خصم الكارت في اي مكان طبي ولا الأماكن الي المتعاقدين معاها فقط؟',
                    'en' => 'Can I use the card discount at any medical facility, or only at contracted providers?',
                ],
                'answer' => [
                    'ar' => 'الكارت بيوفر خصومات في جميع التخصصات الطبية المتعاقدين معهم فقط وبيتم التعاقد بشكل يومي واضافة مقدمي خدمة جدد من ترشيحات عملائنا، كما اننا نهتم بترشيحات عملائنا لزيادة وتوسيع شبكتنا الطبية ونتطلع للحصول علي اكبر شبكة طبية لتقديم خدمة الرعاية الصحية المتميزة لجميع الفئات',
                    'en' => 'The card provides discounts only at contracted medical specialties. We sign new contracts daily and add new providers based on customer recommendations. We value our customers\' suggestions to expand our medical network and aim to build the largest medical network to deliver outstanding healthcare to all.',
                ],
            ],
        ];

        foreach ($items as $index => $item) {
            Faq::updateOrCreate(
                [
                    'question->ar' => $item['question']['ar'],
                ],
                [
                    'question' => $item['question'],
                    'answer' => $item['answer'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
